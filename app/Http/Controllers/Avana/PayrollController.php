<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\PayrollPeriodResource;
use App\Models\Attendance;
use App\Models\BpjsProgram;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use App\Models\Pph21TerRate;
use App\Models\TaxProfile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    use AuthorizesRequests;

    /**
     * Supported payroll cycles (period length presets).
     *
     * @var array<int, string>
     */
    private const CYCLES = ['monthly', 'weekly', 'biweekly'];

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

        return Inertia::render('avana/payroll/index', [
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

        $period = $this->resolveTargetPeriod($tenantId);

        abort_if($period === null, 404);

        if ($period->status === 'locked') {
            return back()->withErrors(['payroll' => 'Periode terkunci, tidak bisa dihitung ulang.']);
        }

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
                    'bpjs_employee_total' => $pay['bpjs_employee'],
                    'bpjs_company_total' => $pay['bpjs_company'],
                    'pph21_total' => $pay['pph21'],
                    'net_salary' => $pay['net'],
                    'calculation_snapshot' => [
                        'earnings' => $pay['earnings'],
                        'deductions' => $pay['deductions'],
                        'present_days' => $pay['present_days'],
                        'overtime_hours' => $pay['overtime_hours'],
                        'gross' => $pay['gross'],
                        'deduction' => $pay['deduction'],
                        'bpjs' => $pay['bpjs_snapshot'],
                        'tax' => $pay['tax_snapshot'],
                        'net' => $pay['net'],
                    ],
                    'status' => 'calculated',
                ],
            );

            $totalGross += $pay['gross'];
            $totalDeduction += $pay['deduction'];
            $totalTax += $pay['pph21'];
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
     * Render the standalone "create payroll period" page.
     */
    public function createPeriod(): Response
    {
        $this->authorize('run', PayrollPeriod::class);

        return Inertia::render('avana/payroll/period-create');
    }

    /**
     * Create a new draft payroll period for a given cycle and date range.
     * Weekly/biweekly periods reuse the same engine — pay components keyed on
     * present-days/overtime are counted within the period's date window.
     */
    public function storePeriod(Request $request): RedirectResponse
    {
        $this->authorize('run', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cycle' => ['required', Rule::in(self::CYCLES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['nullable', 'date'],
        ]);

        PayrollPeriod::create([
            'tenant_id' => $tenantId,
            'code' => $this->generatePeriodCode($tenantId, $data['cycle'], $data['start_date']),
            'name' => $data['name'],
            'cycle' => $data['cycle'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'pay_date' => $data['pay_date'] ?? null,
            'status' => 'draft',
        ]);

        return back()->with('success', 'Periode payroll dibuat');
    }

    /**
     * Build a unique per-tenant period code from its cycle and start date.
     */
    private function generatePeriodCode(int $tenantId, string $cycle, string $start): string
    {
        $prefix = ['monthly' => 'MN', 'weekly' => 'WK', 'biweekly' => 'BW'][$cycle] ?? 'PR';
        $base = $prefix.'-'.Carbon::parse($start)->format('Ymd');

        $code = $base;
        $suffix = 1;

        while (PayrollPeriod::forTenant($tenantId)->where('code', $code)->exists()) {
            $code = $base.'-'.(++$suffix);
        }

        return $code;
    }

    /**
     * Generate a prorated THR (religious holiday allowance) run.
     *
     * THR per employee = monthly base x min(1, monthsWorked / 12). Employees
     * with at least a full year of tenure receive a whole month's base; newer
     * hires receive a proportionally smaller amount.
     */
    public function thr(Request $request): RedirectResponse
    {
        $this->authorize('run', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        // Compute every employee's monthly bruto against the latest regular
        // (non-THR) period; fall back to the THR period when none exists.
        $basePeriod = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->orderByDesc('start_date')
            ->first();

        $year = (int) now()->year;

        $period = PayrollPeriod::firstOrNew([
            'tenant_id' => $tenantId,
            'code' => 'THR-'.$year,
        ]);
        $period->name = 'THR '.$year;
        $period->start_date = $year.'-01-01';
        $period->end_date = $year.'-12-31';
        $period->pay_date = now()->toDateString();
        $period->status = 'draft';
        $period->save();

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

        $totalThr = 0.0;
        $count = 0;

        foreach ($employees as $employee) {
            $pay = $this->computeEmployeePay($employee, $basePeriod ?? $period, $tenantId);
            $base = $pay['gross'] > 0 ? $pay['gross'] : $pay['basic'];

            $monthsWorked = $employee->join_date !== null
                ? (int) abs($employee->join_date->diffInMonths(now()))
                : 12;

            $factor = min(1.0, $monthsWorked / 12);
            $thr = round($base * $factor);

            PayrollRunItem::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                [
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'gross_salary' => $thr,
                    'total_allowance' => $thr,
                    'total_deduction' => 0,
                    'bpjs_employee_total' => 0,
                    'bpjs_company_total' => 0,
                    'pph21_total' => 0,
                    'net_salary' => $thr,
                    'calculation_snapshot' => [
                        'months_worked' => $monthsWorked,
                        'base' => $base,
                        'factor' => $factor,
                        'thr' => $thr,
                        'formula' => 'THR = base x min(1, months_worked / 12)',
                    ],
                    'status' => 'calculated',
                ],
            );

            $totalThr += $thr;
            $count++;
        }

        $run->update([
            'total_gross' => $totalThr,
            'total_deduction' => 0,
            'total_tax' => 0,
            'total_net' => $totalThr,
            'employee_count' => $count,
            'status' => 'calculated',
        ]);

        return back()->with('success', 'THR dihitung — total '.$this->rupiah($totalThr));
    }

    /**
     * Export the bank transfer file (net pay per employee) for the latest run.
     */
    public function transferFile(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $run = PayrollRun::forTenant($tenantId)
            ->whereHas('period', fn ($query) => $query->where('code', 'not like', 'THR-%'))
            ->orderByDesc('id')
            ->with(['period', 'items.employee.bankAccounts'])
            ->first();

        abort_if($run === null, 404);

        $periodCode = $run->period?->code ?? 'run-'.$run->id;
        $filename = 'transfer-bank-'.$periodCode.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($run): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nama', 'Bank', 'No Rekening', 'Atas Nama', 'Net']);

            foreach ($run->items as $item) {
                $employee = $item->employee;
                $bank = $employee?->bankAccounts->firstWhere('is_primary', true)
                    ?? $employee?->bankAccounts->first();

                fputcsv($out, [
                    $employee?->full_name ?? '-',
                    $bank?->bank_name ?? '-',
                    $bank?->account_number ?? '-',
                    $bank?->account_holder ?? $employee?->full_name ?? '-',
                    (int) round((float) $item->net_salary),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Lock the latest payroll run/period so figures can no longer be recomputed.
     */
    public function lock(Request $request): RedirectResponse
    {
        $this->authorize('run', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $period = $this->resolveTargetPeriod($tenantId);

        abort_if($period === null, 404);

        PayrollRun::forTenant($tenantId)
            ->where('payroll_period_id', $period->id)
            ->orderByDesc('id')
            ->first()
            ?->update(['status' => 'locked']);

        $period->update(['status' => 'locked']);

        return back()->with('success', 'Payroll dikunci');
    }

    /**
     * Resolve the period payroll actions should target: the latest draft period,
     * falling back to the most recent period overall.
     */
    private function resolveTargetPeriod(int $tenantId): ?PayrollPeriod
    {
        return PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->where('status', 'draft')
            ->orderByDesc('start_date')
            ->first()
            ?? PayrollPeriod::forTenant($tenantId)
                ->where('code', 'not like', 'THR-%')
                ->orderByDesc('start_date')
                ->first();
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
        $overtimeHours = 0.0;

        if ($period->start_date !== null && $period->end_date !== null) {
            $range = [$period->start_date->toDateString(), $period->end_date->toDateString()];

            $presentDays = Attendance::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->whereBetween('date', $range)
                ->where('status', 'present')
                ->count();

            $overtimeHours = (float) OvertimeRequest::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', $range)
                ->sum('hours');
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

                $amount = $this->amountForBasis((float) $positionComponent->amount, $component->calc_basis, $presentDays, $overtimeHours);
                $this->collectComponent($component, $amount, $earnings, $deductions, $basic);
            }
        }

        $gross = (float) array_sum(array_column($earnings, 'amount'));

        // Statutory deductions computed from internal config (no external API).
        $bpjs = $this->computeBpjs($employee, $tenantId, $basic > 0 ? $basic : $gross);
        $pph21 = $this->computePph21($employee, $tenantId, $gross);

        if ($bpjs['employee'] > 0) {
            $deductions[] = ['name' => 'BPJS (Karyawan)', 'amount' => $bpjs['employee']];
        }

        if ($pph21['amount'] > 0) {
            $deductions[] = ['name' => 'PPh 21', 'amount' => $pph21['amount']];
        }

        $deduction = (float) array_sum(array_column($deductions, 'amount'));

        return [
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross' => $gross,
            'deduction' => $deduction,
            'net' => $gross - $deduction,
            'present_days' => $presentDays,
            'overtime_hours' => $overtimeHours,
            'basic' => $basic,
            'bpjs_employee' => $bpjs['employee'],
            'bpjs_company' => $bpjs['company'],
            'bpjs_snapshot' => $bpjs['snapshot'],
            'pph21' => $pph21['amount'],
            'tax_snapshot' => $pph21['snapshot'],
        ];
    }

    /**
     * Resolve a position component amount for its attendance calculation basis.
     */
    private function amountForBasis(float $amount, ?string $calcBasis, int $presentDays, float $overtimeHours = 0.0): float
    {
        return match ($calcBasis) {
            'per_present_day' => $amount * $presentDays,
            'per_overtime_hour' => $amount * $overtimeHours,
            default => $amount,
        };
    }

    /**
     * Compute the BPJS employee/company contribution from active internal rates.
     *
     * @return array{employee: float, company: float, snapshot: array<string, mixed>}
     */
    private function computeBpjs(Employee $employee, int $tenantId, float $fallbackWage): array
    {
        $profile = EmployeeBpjsProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        // BPJS only applies to enrolled employees (those with a profile).
        if ($profile === null) {
            return ['employee' => 0.0, 'company' => 0.0, 'snapshot' => []];
        }

        $base = (float) $profile->registered_wage > 0
            ? (float) $profile->registered_wage
            : $fallbackWage;

        $enabledMap = [
            'kesehatan' => 'kesehatan_enabled',
            'jht' => 'jht_enabled',
            'jp' => 'jp_enabled',
            'jkk' => 'jkk_enabled',
            'jkm' => 'jkm_enabled',
        ];

        $employeeTotal = 0.0;
        $companyTotal = 0.0;
        $lines = [];

        $programs = BpjsProgram::where('is_active', true)
            ->with(['rates' => fn ($query) => $query->where('is_active', true)->orderByDesc('effective_start_date')])
            ->get();

        foreach ($programs as $program) {
            $code = strtolower((string) $program->code);

            if ($profile !== null && isset($enabledMap[$code]) && ! $profile->{$enabledMap[$code]}) {
                continue;
            }

            $rate = $program->rates->first();

            if ($rate === null) {
                continue;
            }

            $capped = (float) $rate->max_wage > 0 ? min($base, (float) $rate->max_wage) : $base;
            $employeePortion = round((float) $rate->employee_rate * $capped);
            $companyPortion = round((float) $rate->company_rate * $capped);

            $employeeTotal += $employeePortion;
            $companyTotal += $companyPortion;
            $lines[$code] = ['employee' => $employeePortion, 'company' => $companyPortion];
        }

        return [
            'employee' => $employeeTotal,
            'company' => $companyTotal,
            'snapshot' => ['base_wage' => $base, 'programs' => $lines],
        ];
    }

    /**
     * Compute internal PPh 21 using the TER bracket matching the gross income.
     *
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    private function computePph21(Employee $employee, int $tenantId, float $gross): array
    {
        $profile = TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        $category = $profile?->tax_category ?: 'A';

        $rate = Pph21TerRate::where('is_active', true)
            ->where('category', $category)
            ->where('income_min', '<=', $gross)
            ->where(function ($query) use ($gross): void {
                $query->where('income_max', '>=', $gross)
                    ->orWhereNull('income_max')
                    ->orWhere('income_max', 0);
            })
            ->orderByDesc('income_min')
            ->first();

        $taxRate = $rate !== null ? (float) $rate->rate : 0.0;
        $amount = round($taxRate * $gross);

        return [
            'amount' => $amount,
            'snapshot' => [
                'ptkp_status' => $profile?->ptkp_status,
                'ter_category' => $category,
                'tax_rate' => $taxRate,
                'pph21_amount' => $amount,
            ],
        ];
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
