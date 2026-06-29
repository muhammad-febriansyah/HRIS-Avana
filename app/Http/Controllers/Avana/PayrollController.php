<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\PayrollPeriodResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the payroll periods list, latest-run summary and a sample payslip.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $periods = PayrollPeriod::forTenant($tenantId)
            ->withCount('runs')
            ->with(['runs' => fn ($query) => $query->orderByDesc('id')])
            ->orderByDesc('start_date')
            ->paginate(10)
            ->withQueryString();

        $latestPeriod = PayrollPeriod::forTenant($tenantId)
            ->with(['runs' => fn ($query) => $query->orderByDesc('id')])
            ->orderByDesc('start_date')
            ->first();

        $latestRun = $latestPeriod?->runs->first();

        return Inertia::render('avana/payroll', [
            'periods' => PayrollPeriodResource::collection($periods),
            'summary' => [
                'period' => $latestPeriod?->name,
                'status' => $latestRun?->status ?? $latestPeriod?->status,
                'status_label' => PayrollPeriodResource::statusLabel($latestRun?->status ?? $latestPeriod?->status),
                'total_gross' => $this->rupiah($latestRun?->total_gross ?? 0),
                'total_deduction' => $this->rupiah($latestRun?->total_deduction ?? 0),
                'total_tax' => $this->rupiah($latestRun?->total_tax ?? 0),
                'total_net' => $this->rupiah($latestRun?->total_net ?? 0),
                'employee_count' => (int) ($latestRun?->employee_count ?? 0),
            ],
            'slip' => $this->buildSampleSlip($tenantId, $latestPeriod),
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    /**
     * Create or refresh the payroll run for the current draft period and
     * recompute every active employee's run item.
     */
    public function run(Request $request): RedirectResponse
    {
        $this->authorize('run', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $period = PayrollPeriod::forTenant($tenantId)
            ->where('status', 'draft')
            ->orderByDesc('start_date')
            ->first()
            ?? PayrollPeriod::forTenant($tenantId)->orderByDesc('start_date')->first();

        abort_if($period === null, 404);

        $run = PayrollRun::firstOrNew([
            'tenant_id' => $tenantId,
            'payroll_period_id' => $period->id,
            'branch_id' => null,
        ]);
        $run->status = 'calculated';
        $run->save();

        $employees = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->get();

        $totalGross = 0.0;
        $totalDeduction = 0.0;
        $totalTax = 0.0;
        $totalNet = 0.0;
        $count = 0;

        foreach ($employees as $employee) {
            $pay = $this->computeEmployeePay($employee, $period, $tenantId);

            PayrollRunItem::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                [
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'gross_salary' => $pay['gross'],
                    'total_allowance' => max(0.0, $pay['gross'] - $pay['basic']),
                    'total_deduction' => $pay['deduction'],
                    'bpjs_employee_total' => 0,
                    'bpjs_company_total' => 0,
                    'pph21_total' => 0,
                    'net_salary' => $pay['net'],
                    'calculation_snapshot' => [
                        'earnings' => $pay['earnings'],
                        'deductions' => $pay['deductions'],
                        'present_days' => $pay['present_days'],
                        'gross' => $pay['gross'],
                        'deduction' => $pay['deduction'],
                        'net' => $pay['net'],
                    ],
                    'status' => 'calculated',
                ],
            );

            $totalGross += $pay['gross'];
            $totalDeduction += $pay['deduction'];
            $totalNet += $pay['net'];
            $count++;
        }

        $run->update([
            'total_gross' => $totalGross,
            'total_deduction' => $totalDeduction,
            'total_tax' => $totalTax,
            'total_net' => $totalNet,
            'employee_count' => $count,
            'status' => 'calculated',
        ]);

        return back()->with('success', 'Payroll dihitung');
    }

    /**
     * Build a computed sample payslip for the first active employee, falling
     * back to a representative example when no components are configured.
     *
     * @return array<string, mixed>
     */
    private function buildSampleSlip(int $tenantId, ?PayrollPeriod $period): array
    {
        $employee = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($employee !== null && $period !== null) {
            $pay = $this->computeEmployeePay($employee, $period, $tenantId);

            if ($pay['earnings'] !== [] || $pay['deductions'] !== []) {
                return [
                    'employee' => $employee->full_name,
                    'earnings' => array_map(
                        fn (array $row): array => ['k' => $row['name'], 'v' => $this->rupiah($row['amount'])],
                        $pay['earnings'],
                    ),
                    'deductions' => array_map(
                        fn (array $row): array => ['k' => $row['name'], 'v' => $this->rupiah($row['amount'])],
                        $pay['deductions'],
                    ),
                    'gross' => $this->rupiah($pay['gross']),
                    'deduction' => $this->rupiah($pay['deduction']),
                    'net' => $this->rupiah($pay['net']),
                ];
            }
        }

        return [
            'employee' => $employee?->full_name ?? 'Contoh Karyawan',
            'earnings' => [
                ['k' => 'Gaji Pokok', 'v' => $this->rupiah(5_000_000)],
                ['k' => 'Tunjangan Jabatan', 'v' => $this->rupiah(1_000_000)],
                ['k' => 'Tunjangan Transport', 'v' => $this->rupiah(500_000)],
            ],
            'deductions' => [
                ['k' => 'Potongan Koperasi', 'v' => $this->rupiah(200_000)],
                ['k' => 'BPJS (Karyawan)', 'v' => $this->rupiah(150_000)],
            ],
            'gross' => $this->rupiah(6_500_000),
            'deduction' => $this->rupiah(350_000),
            'net' => $this->rupiah(6_150_000),
        ];
    }

    /**
     * Compute the earnings, deductions and totals for one employee in a period.
     *
     * Attendance-linked position components are scaled by the period's
     * attendance metric (present-day count, or overtime hours — 0 for now).
     *
     * @return array{
     *     earnings: list<array{name: string, amount: float}>,
     *     deductions: list<array{name: string, amount: float}>,
     *     gross: float,
     *     deduction: float,
     *     net: float,
     *     present_days: int,
     *     basic: float,
     * }
     */
    private function computeEmployeePay(Employee $employee, PayrollPeriod $period, int $tenantId): array
    {
        $presentDays = 0;

        if ($period->start_date !== null && $period->end_date !== null) {
            $presentDays = Attendance::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->where('status', 'present')
                ->count();
        }

        /** @var list<array{name: string, amount: float}> $earnings */
        $earnings = [];
        /** @var list<array{name: string, amount: float}> $deductions */
        $deductions = [];
        $basic = 0.0;

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->with('component')
            ->get();

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component === null) {
                continue;
            }

            $this->collectComponent($component, (float) $salaryComponent->amount, $earnings, $deductions, $basic);
        }

        if ($employee->position_id !== null) {
            $positionComponents = PositionPayrollComponent::forTenant($tenantId)
                ->where('position_id', $employee->position_id)
                ->with('component')
                ->get();

            foreach ($positionComponents as $positionComponent) {
                $component = $positionComponent->component;

                if ($component === null) {
                    continue;
                }

                $amount = $this->amountForBasis((float) $positionComponent->amount, $component->calc_basis, $presentDays);
                $this->collectComponent($component, $amount, $earnings, $deductions, $basic);
            }
        }

        $gross = (float) array_sum(array_column($earnings, 'amount'));
        $deduction = (float) array_sum(array_column($deductions, 'amount'));

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross' => $gross,
            'deduction' => $deduction,
            'net' => $gross - $deduction,
            'present_days' => $presentDays,
            'basic' => $basic,
        ];
    }

    /**
     * Resolve a position component amount for its attendance calculation basis.
     */
    private function amountForBasis(float $amount, ?string $calcBasis, int $presentDays): float
    {
        return match ($calcBasis) {
            'per_present_day' => $amount * $presentDays,
            'per_overtime_hour' => 0.0,
            default => $amount,
        };
    }

    /**
     * Push a component's resolved amount into the earnings or deductions bucket.
     *
     * @param  list<array{name: string, amount: float}>  $earnings
     * @param  list<array{name: string, amount: float}>  $deductions
     */
    private function collectComponent(PayrollComponent $component, float $amount, array &$earnings, array &$deductions, float &$basic): void
    {
        $row = ['name' => (string) $component->name, 'amount' => $amount];

        if ($component->type === 'deduction') {
            $deductions[] = $row;

            return;
        }

        $earnings[] = $row;

        if ($component->code === 'BASIC') {
            $basic += $amount;
        }
    }

    /**
     * Format a numeric value as an Indonesian rupiah string.
     */
    private function rupiah(int|float|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
