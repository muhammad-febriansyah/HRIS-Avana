<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\PayrollPeriodResource;
use App\Models\Attendance;
use App\Models\BpjsProgram;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use App\Models\Pph21TerRate;
use App\Models\TaxProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
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

        $employees = $this->payableEmployees($tenantId, $period);

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
                        'proration_factor' => $pay['proration_factor'],
                        'loan_ids' => $pay['loan_ids'],
                        'advance_ids' => $pay['advance_ids'],
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
            ->where(function ($query): void {
                $query->where('status', 'active')
                    // Permenaker 6/2016: employees who resigned within 30 days
                    // before the THR payout remain entitled to a prorated THR.
                    ->orWhere(fn ($sub) => $sub
                        ->whereNotNull('resign_date')
                        ->where('resign_date', '>=', now()->subDays(30)->toDateString()));
            })
            ->get();

        $totalThr = 0.0;
        $count = 0;

        foreach ($employees as $employee) {
            $monthsWorked = $employee->join_date !== null
                ? (int) floor(abs($employee->join_date->diffInMonths(now())))
                : 12;

            // Permenaker 6/2016: minimum one continuous month of service.
            if ($monthsWorked < 1) {
                continue;
            }

            // THR is based on a full month's wage, never the prorated payslip.
            $base = $this->monthlyBaseWage($employee, $tenantId);

            if ($base <= 0) {
                $pay = $this->computeEmployeePay($employee, $basePeriod ?? $period, $tenantId);
                $base = $pay['basic'] > 0 ? $pay['basic'] : $pay['gross'];
            }

            $factor = min(1.0, $monthsWorked / 12);
            $thr = round($base * $factor);

            if ($thr <= 0) {
                continue;
            }

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
     * Supported bank transfer file layouts. Each entry defines the CSV header
     * and the ordered columns produced per employee. `generic` is the default.
     *
     * @var array<string, array{header: list<string>}>
     */
    private const BANK_FORMATS = [
        'generic' => ['header' => ['Nama', 'Bank', 'No Rekening', 'Atas Nama', 'Net']],
        'bca' => ['header' => ['No Rekening Tujuan', 'Nama Penerima', 'Jumlah', 'Berita']],
        'mandiri' => ['header' => ['No Rekening', 'Nama Penerima', 'Jumlah', 'Keterangan']],
        'bni' => ['header' => ['No Rekening', 'Nama', 'Nominal', 'Keterangan']],
        'bri' => ['header' => ['No Rekening', 'Nama', 'Nominal', 'Keterangan']],
    ];

    /**
     * Export the bank transfer file (net pay per employee) for the latest run,
     * in a selectable per-bank column layout (?bank=bca|mandiri|bni|bri).
     */
    public function transferFile(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $format = $request->query('bank', 'generic');
        $format = isset(self::BANK_FORMATS[$format]) ? $format : 'generic';
        $header = self::BANK_FORMATS[$format]['header'];

        $run = PayrollRun::forTenant($tenantId)
            ->whereHas('period', fn ($query) => $query->where('code', 'not like', 'THR-%'))
            ->orderByDesc('id')
            ->with(['period', 'items.employee.bankAccounts'])
            ->first();

        abort_if($run === null, 404);

        $periodCode = $run->period?->code ?? 'run-'.$run->id;
        $note = 'Gaji '.($run->period?->name ?? $periodCode);
        $filename = 'transfer-'.$format.'-'.$periodCode.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($run, $format, $header, $note): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($run->items as $item) {
                $employee = $item->employee;
                $bank = $employee?->bankAccounts->firstWhere('is_primary', true)
                    ?? $employee?->bankAccounts->first();

                $name = $bank?->account_holder ?? $employee?->full_name ?? '-';
                $account = $bank?->account_number ?? '-';
                $net = (int) round((float) $item->net_salary);

                $row = match ($format) {
                    'bca' => [$account, $name, $net, $note],
                    'mandiri', 'bni', 'bri' => [$account, $name, $net, $note],
                    default => [
                        $employee?->full_name ?? '-',
                        $bank?->bank_name ?? '-',
                        $account,
                        $name,
                        $net,
                    ],
                };

                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Export the BPJS contribution report (employee/company split per program)
     * for the latest run — the data needed to file via SIPP/EDABU (BR-12.3).
     */
    public function bpjsFile(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $run = PayrollRun::forTenant($tenantId)
            ->whereHas('period', fn ($query) => $query->where('code', 'not like', 'THR-%'))
            ->orderByDesc('id')
            ->with(['period', 'items.employee'])
            ->first();

        abort_if($run === null, 404);

        $periodCode = $run->period?->code ?? 'run-'.$run->id;
        $filename = 'bpjs-'.$periodCode.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($run): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Nama', 'No Karyawan', 'Upah Dilaporkan',
                'Kesehatan (Karyawan)', 'Kesehatan (Perusahaan)',
                'JHT (Karyawan)', 'JHT (Perusahaan)',
                'JP (Karyawan)', 'JP (Perusahaan)',
                'JKK (Perusahaan)', 'JKM (Perusahaan)',
                'Total Karyawan', 'Total Perusahaan',
            ]);

            foreach ($run->items as $item) {
                $employee = $item->employee;
                $bpjs = $item->calculation_snapshot['bpjs'] ?? [];
                $programs = $bpjs['programs'] ?? [];
                $cell = fn (string $code, string $side): int => (int) round((float) ($programs[$code][$side] ?? 0));

                fputcsv($out, [
                    $employee?->full_name ?? '-',
                    $employee?->employee_number ?? '-',
                    (int) round((float) ($bpjs['base_wage'] ?? 0)),
                    $cell('kesehatan', 'employee'), $cell('kesehatan', 'company'),
                    $cell('jht', 'employee'), $cell('jht', 'company'),
                    $cell('jp', 'employee'), $cell('jp', 'company'),
                    $cell('jkk', 'company'), $cell('jkm', 'company'),
                    (int) round((float) $item->bpjs_employee_total),
                    (int) round((float) $item->bpjs_company_total),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Stream a single employee's payslip as a password-protected PDF (BR-11.4).
     * The password defaults to the employee's birth date (ddmmyyyy).
     */
    public function payslipPdf(Request $request, PayrollRunItem $item): \Illuminate\Http\Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        abort_if((int) $item->tenant_id !== (int) $request->user()->tenant_id, 404);

        $item->loadMissing(['employee.position', 'employee.department', 'employee.tenant', 'period']);
        $employee = $item->employee;

        abort_if($employee === null, 404);

        $snapshot = $item->calculation_snapshot ?? [];

        $html = view('pdf.payslip', [
            'company' => $employee->tenant?->company_name ?? $employee->tenant?->name ?? 'AvanaHR',
            'period' => $item->period?->name ?? '-',
            'employee' => [
                'name' => $employee->full_name,
                'number' => $employee->nik ?: $employee->employee_number,
                'position' => $employee->position?->name ?? '-',
                'department' => $employee->department?->name ?? '-',
            ],
            'earnings' => array_map(
                fn (array $row): array => ['name' => $row['name'], 'amount' => $this->rupiah($row['amount'])],
                $snapshot['earnings'] ?? [],
            ),
            'deductions' => array_map(
                fn (array $row): array => ['name' => $row['name'], 'amount' => $this->rupiah($row['amount'])],
                $snapshot['deductions'] ?? [],
            ),
            'gross' => $this->rupiah($item->gross_salary),
            'deduction' => $this->rupiah($item->total_deduction),
            'net' => $this->rupiah($item->net_salary),
        ])->render();

        $tempDir = storage_path('app/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf(['tempDir' => $tempDir]);
        $mpdf->SetProtection(['print'], $this->payslipPassword($employee), '');
        $mpdf->WriteHTML($html);

        $filename = 'slip-'.$employee->employee_number.'-'.($item->period?->code ?? $item->id).'.pdf';

        return response($mpdf->Output('', Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Generate an annual PPh 21 withholding slip (form 1721-A1) for an employee,
     * aggregating every run item in the requested tax year.
     */
    public function taxForm1721(Request $request, Employee $employee): \Illuminate\Http\Response
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = (int) $request->user()->tenant_id;

        abort_if((int) $employee->tenant_id !== $tenantId, 404);

        $year = (int) $request->query('year', (string) now()->year);

        $items = PayrollRunItem::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->whereHas('period', fn ($query) => $query->whereYear('start_date', $year))
            ->get(['gross_salary', 'pph21_total', 'bpjs_employee_total']);

        $employee->loadMissing(['position', 'tenant', 'taxProfile']);

        $annualGross = (float) $items->sum('gross_salary');
        $withheld = (float) $items->sum('pph21_total');
        $biayaJabatan = min($annualGross * 0.05, 6_000_000);
        $ptkpStatus = $employee->taxProfile?->ptkp_status;
        $ptkp = $this->ptkpFor($ptkpStatus);
        $pkp = max(0.0, floor(($annualGross - $biayaJabatan - $ptkp) / 1000) * 1000);
        $annualTax = $this->progressiveTax($pkp);

        $pdf = Pdf::loadView('pdf.bukti-potong-1721', [
            'company' => $employee->tenant?->company_name ?? $employee->tenant?->name ?? 'AvanaHR',
            'year' => $year,
            'employee' => [
                'name' => $employee->full_name,
                'nik' => $employee->nik ?: '-',
                'number' => $employee->employee_number,
                'position' => $employee->position?->name ?? '-',
                'ptkp' => $ptkpStatus ?? 'TK/0',
            ],
            'rows' => [
                ['Penghasilan Bruto Setahun', $this->rupiah($annualGross)],
                ['Pengurangan — Biaya Jabatan', $this->rupiah($biayaJabatan)],
                ['Penghasilan Neto', $this->rupiah($annualGross - $biayaJabatan)],
                ['PTKP ('.($ptkpStatus ?? 'TK/0').')', $this->rupiah($ptkp)],
                ['Penghasilan Kena Pajak (PKP)', $this->rupiah($pkp)],
                ['PPh 21 Terutang Setahun', $this->rupiah($annualTax)],
                ['PPh 21 Telah Dipotong', $this->rupiah($withheld)],
            ],
        ])->setPaper('a4');

        return $pdf->download('1721-A1-'.$employee->employee_number.'-'.$year.'.pdf');
    }

    /**
     * Derive the payslip PDF password: birth date (ddmmyyyy), else NIK/number.
     */
    private function payslipPassword(Employee $employee): string
    {
        if ($employee->birth_date !== null) {
            return $employee->birth_date->format('dmY');
        }

        return (string) ($employee->nik ?: $employee->employee_number ?: 'avanahr');
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

        $run = PayrollRun::forTenant($tenantId)
            ->where('payroll_period_id', $period->id)
            ->orderByDesc('id')
            ->first();

        // Finalizing advances loan/cash-advance installments exactly once.
        if ($run !== null && $run->status !== 'locked') {
            $this->advanceInstallments($run, $tenantId);
            $run->update(['status' => 'locked']);
        }

        $period->update(['status' => 'locked']);

        return back()->with('success', 'Payroll dikunci');
    }

    /**
     * Advance the installment counters for every loan/cash-advance that was
     * deducted in this run, settling them once fully paid.
     */
    private function advanceInstallments(PayrollRun $run, int $tenantId): void
    {
        $items = PayrollRunItem::forTenant($tenantId)
            ->where('payroll_run_id', $run->id)
            ->get(['calculation_snapshot']);

        $loanIds = [];
        $advanceIds = [];

        foreach ($items as $item) {
            $snapshot = $item->calculation_snapshot ?? [];

            foreach ((array) ($snapshot['loan_ids'] ?? []) as $id) {
                $loanIds[] = (int) $id;
            }

            foreach ((array) ($snapshot['advance_ids'] ?? []) as $id) {
                $advanceIds[] = (int) $id;
            }
        }

        foreach (Loan::forTenant($tenantId)->whereIn('id', array_unique($loanIds))->get() as $loan) {
            $paid = min((int) $loan->tenor_months, (int) $loan->paid_installments + 1);
            $loan->paid_installments = $paid;

            if ($paid >= (int) $loan->tenor_months) {
                $loan->status = 'paid';
            }

            $loan->save();
        }

        foreach (CashAdvance::forTenant($tenantId)->whereIn('id', array_unique($advanceIds))->get() as $advance) {
            $paid = min((int) $advance->installments, (int) $advance->paid_installments + 1);
            $advance->paid_installments = $paid;

            if ($paid >= (int) $advance->installments) {
                $advance->status = 'paid';
            }

            $advance->save();
        }
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
                // A saved run item enables a downloadable, protected PDF payslip.
                $payslipId = PayrollRunItem::forTenant($tenantId)
                    ->where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->orderByDesc('id')
                    ->value('id');

                return [
                    'employee' => $employee->full_name,
                    'payslip_id' => $payslipId,
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
        $overtimeRecords = collect();

        if ($period->start_date !== null && $period->end_date !== null) {
            $range = [$period->start_date->toDateString(), $period->end_date->toDateString()];

            $presentDays = Attendance::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->whereBetween('date', $range)
                ->where('status', 'present')
                ->count();

            $overtimeRecords = OvertimeRequest::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', $range)
                ->get(['date', 'hours']);
        }

        $overtimeHours = (float) $overtimeRecords->sum('hours');

        /** @var list<array{name: string, amount: float, proratable: bool}> $earnings */
        $earnings = [];
        /** @var list<array{name: string, amount: float}> $deductions */
        $deductions = [];
        $basic = 0.0;
        $hasCustomOvertime = false;

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->with('component')
            ->get();

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component === null) {
                continue;
            }

            $this->collectComponent($component, (float) $salaryComponent->amount, true, $earnings, $deductions, $basic);
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

                if ($component->calc_basis === 'per_overtime_hour') {
                    $hasCustomOvertime = true;
                }

                $amount = $this->amountForBasis((float) $positionComponent->amount, $component->calc_basis, $presentDays, $overtimeHours);
                $proratable = ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true);
                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic);
            }
        }

        // Capture the full monthly basic before proration for the overtime rate.
        $fullBasic = $basic;

        // Prorate fixed earnings for mid-period joiners/leavers so a resigning
        // employee is still paid — proportionally — for their final month.
        $factor = $this->prorationFactor($employee, $period);

        if ($factor < 1.0) {
            foreach ($earnings as $index => $row) {
                if ($row['proratable']) {
                    $earnings[$index]['amount'] = round($row['amount'] * $factor);
                }
            }

            $basic = round($basic * $factor);
        }

        // Statutory overtime pay (Kepmenaker 102/2004): 1.5x the first hour and
        // 2x subsequent hours of an hourly wage of 1/173 of the monthly wage.
        // Skipped when the tenant already models overtime as a position component.
        if (! $hasCustomOvertime && $overtimeRecords->isNotEmpty() && $fullBasic > 0) {
            $overtimePay = $this->computeOvertimePay($fullBasic, $overtimeRecords);

            if ($overtimePay > 0) {
                $earnings[] = ['name' => 'Lembur', 'amount' => $overtimePay, 'proratable' => false];
            }
        }

        $gross = (float) array_sum(array_column($earnings, 'amount'));

        // Recurring loan & cash-advance installments deducted from take-home pay.
        [$recurring, $loanIds, $advanceIds] = $this->recurringDeductions($employee, $tenantId);

        foreach ($recurring as $line) {
            $deductions[] = $line;
        }

        // Statutory deductions computed from internal config (no external API).
        $bpjs = $this->computeBpjs($employee, $tenantId, $basic > 0 ? $basic : $gross);

        // December (or the employee's final tax month) reconciles the year's
        // withholding against the progressive Pasal 17 tariff; other months use
        // the monthly TER bracket.
        $pph21 = $this->isFinalTaxMonth($employee, $period)
            ? $this->computeAnnualPph21($employee, $tenantId, $period, $gross)
            : $this->computePph21($employee, $tenantId, $gross);

        if ($bpjs['employee'] > 0) {
            $deductions[] = ['name' => 'BPJS (Karyawan)', 'amount' => $bpjs['employee']];
        }

        if ($pph21['amount'] > 0) {
            $deductions[] = ['name' => 'PPh 21', 'amount' => $pph21['amount']];
        }

        $deduction = (float) array_sum(array_column($deductions, 'amount'));

        return [
            'earnings' => array_map(
                static fn (array $row): array => ['name' => $row['name'], 'amount' => $row['amount']],
                $earnings,
            ),
            'deductions' => $deductions,
            'gross' => $gross,
            'deduction' => $deduction,
            'net' => $gross - $deduction,
            'present_days' => $presentDays,
            'overtime_hours' => $overtimeHours,
            'proration_factor' => $factor,
            'loan_ids' => $loanIds,
            'advance_ids' => $advanceIds,
            'basic' => $basic,
            'bpjs_employee' => $bpjs['employee'],
            'bpjs_company' => $bpjs['company'],
            'bpjs_snapshot' => $bpjs['snapshot'],
            'pph21' => $pph21['amount'],
            'tax_snapshot' => $pph21['snapshot'],
        ];
    }

    /**
     * Employees whose pay should be computed for the period: everyone active,
     * plus anyone whose resignation falls inside the period (final-month pay).
     * Excludes those who joined after the period or left before it started.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Employee>
     */
    private function payableEmployees(int $tenantId, PayrollPeriod $period): \Illuminate\Database\Eloquent\Collection
    {
        $start = $period->start_date?->toDateString();
        $end = $period->end_date?->toDateString();

        if ($start === null || $end === null) {
            return Employee::forTenant($tenantId)->where('status', 'active')->get();
        }

        return Employee::forTenant($tenantId)
            // Joined on or before the period ends.
            ->where(fn ($query) => $query->whereNull('join_date')->orWhere('join_date', '<=', $end))
            // Did not leave before the period starts (so the final month is paid).
            ->where(fn ($query) => $query->whereNull('resign_date')->orWhere('resign_date', '>=', $start))
            // Still active, or a leaver whose resignation is dated (covers those
            // already flipped to inactive once their last working day passed).
            ->where(fn ($query) => $query->where('status', 'active')->orWhereNotNull('resign_date'))
            ->get();
    }

    /**
     * Fraction of the period an employee actually worked (1.0 = whole period),
     * based on the overlap of their join/resign dates with the period window.
     */
    private function prorationFactor(Employee $employee, PayrollPeriod $period): float
    {
        if ($period->start_date === null || $period->end_date === null) {
            return 1.0;
        }

        $periodStart = $period->start_date->copy()->startOfDay();
        $periodEnd = $period->end_date->copy()->startOfDay();

        $workStart = $employee->join_date !== null && $employee->join_date->gt($periodStart)
            ? $employee->join_date->copy()->startOfDay()
            : $periodStart;

        $workEnd = $employee->resign_date !== null && $employee->resign_date->lt($periodEnd)
            ? $employee->resign_date->copy()->startOfDay()
            : $periodEnd;

        if ($workEnd->lt($workStart)) {
            return 0.0;
        }

        $calendarDays = $periodStart->diffInDays($periodEnd) + 1;
        $workedDays = $workStart->diffInDays($workEnd) + 1;

        if ($calendarDays <= 0) {
            return 1.0;
        }

        return min(1.0, $workedDays / $calendarDays);
    }

    /**
     * Compute statutory overtime pay across the approved overtime records using
     * the Kepmenaker 102/2004 workday multipliers (1.5x first hour, 2x after).
     *
     * @param  Collection<int, OvertimeRequest>  $records
     */
    private function computeOvertimePay(float $monthlyWage, Collection $records): float
    {
        $hourlyRate = $monthlyWage / 173;
        $total = 0.0;

        foreach ($records as $record) {
            $hours = (float) $record->hours;

            if ($hours <= 0) {
                continue;
            }

            if ($hours <= 1) {
                $total += 1.5 * $hourlyRate * $hours;

                continue;
            }

            $total += 1.5 * $hourlyRate;
            $total += 2 * $hourlyRate * ($hours - 1);
        }

        return round($total);
    }

    /**
     * Build recurring loan/cash-advance deduction lines for the employee and the
     * source record ids so the finalize step can advance their installments.
     *
     * @return array{0: list<array{name: string, amount: float, loan_id?: int, cash_advance_id?: int}>, 1: list<int>, 2: list<int>}
     */
    private function recurringDeductions(Employee $employee, int $tenantId): array
    {
        $lines = [];
        $loanIds = [];
        $advanceIds = [];

        $loans = Loan::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->get();

        foreach ($loans as $loan) {
            $remaining = (int) $loan->tenor_months - (int) $loan->paid_installments;

            if ($remaining > 0 && (float) $loan->monthly_installment > 0) {
                $lines[] = ['name' => 'Cicilan Pinjaman', 'amount' => (float) $loan->monthly_installment, 'loan_id' => $loan->id];
                $loanIds[] = $loan->id;
            }
        }

        $advances = CashAdvance::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->get();

        foreach ($advances as $advance) {
            $remaining = (int) $advance->installments - (int) $advance->paid_installments;

            if ($remaining > 0 && (float) $advance->monthly_deduction > 0) {
                $lines[] = ['name' => 'Potongan Kasbon', 'amount' => (float) $advance->monthly_deduction, 'cash_advance_id' => $advance->id];
                $advanceIds[] = $advance->id;
            }
        }

        return [$lines, $loanIds, $advanceIds];
    }

    /**
     * Sum an employee's full monthly fixed wage (earnings only, no proration and
     * no attendance-variable components) — the basis for THR and severance.
     */
    private function monthlyBaseWage(Employee $employee, int $tenantId): float
    {
        $total = 0.0;

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->with('component')
            ->get();

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component !== null && $component->type !== 'deduction') {
                $total += (float) $salaryComponent->amount;
            }
        }

        if ($employee->position_id !== null) {
            $positionComponents = PositionPayrollComponent::forTenant($tenantId)
                ->where('position_id', $employee->position_id)
                ->with('component')
                ->get();

            foreach ($positionComponents as $positionComponent) {
                $component = $positionComponent->component;

                if ($component !== null
                    && $component->type !== 'deduction'
                    && ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true)) {
                    $total += (float) $positionComponent->amount;
                }
            }
        }

        return $total;
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
     * Whether this period is the employee's last taxable month of the year —
     * December, or the month their resignation falls in — and thus needs an
     * annual reconciliation rather than a flat monthly TER deduction.
     */
    private function isFinalTaxMonth(Employee $employee, PayrollPeriod $period): bool
    {
        if ($period->end_date === null) {
            return false;
        }

        if ((int) $period->end_date->month === 12) {
            return true;
        }

        return $employee->resign_date !== null
            && $employee->resign_date->between($period->start_date, $period->end_date);
    }

    /**
     * Reconcile the annual PPh 21 (progressive Pasal 17 tariff on year-to-date
     * income less biaya jabatan and PTKP) against tax already withheld this year.
     * The final-month deduction is the remaining balance.
     *
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    private function computeAnnualPph21(Employee $employee, int $tenantId, PayrollPeriod $period, float $currentGross): array
    {
        $profile = TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        $year = (int) ($period->start_date?->year ?? now()->year);

        // Year-to-date figures from every earlier run item this calendar year.
        $prior = PayrollRunItem::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('payroll_period_id', '!=', $period->id)
            ->whereHas('period', fn ($query) => $query->whereYear('start_date', $year))
            ->get(['gross_salary', 'pph21_total']);

        $ytdGross = (float) $prior->sum('gross_salary');
        $ytdWithheld = (float) $prior->sum('pph21_total');

        $annualGross = $ytdGross + $currentGross;
        $biayaJabatan = min($annualGross * 0.05, 6_000_000);
        $ptkp = $this->ptkpFor($profile?->ptkp_status);

        $pkp = max(0.0, $annualGross - $biayaJabatan - $ptkp);
        $pkp = floor($pkp / 1000) * 1000; // taxable income is floored to thousands

        $annualTax = $this->progressiveTax($pkp);
        $amount = max(0.0, round($annualTax - $ytdWithheld));

        return [
            'amount' => $amount,
            'snapshot' => [
                'method' => 'annual_reconciliation',
                'ptkp_status' => $profile?->ptkp_status,
                'annual_gross' => round($annualGross),
                'biaya_jabatan' => round($biayaJabatan),
                'ptkp' => $ptkp,
                'pkp' => $pkp,
                'annual_tax' => $annualTax,
                'ytd_withheld' => round($ytdWithheld),
                'pph21_amount' => $amount,
            ],
        ];
    }

    /**
     * Resolve the annual PTKP allowance from a status code such as TK/0 or K/3
     * (base 54jt, +4,5jt married, +4,5jt per dependant capped at three).
     */
    private function ptkpFor(?string $status): float
    {
        $base = 54_000_000.0;

        if ($status === null || $status === '') {
            return $base;
        }

        $status = strtoupper(trim($status));
        $married = str_starts_with($status, 'K');
        $dependents = preg_match('/(\d+)/', $status, $matches) ? min((int) $matches[1], 3) : 0;

        return $base + ($married ? 4_500_000 : 0) + 4_500_000 * $dependents;
    }

    /**
     * Progressive annual income tax on taxable income (PKP) using the UU HPP
     * Pasal 17 brackets: 5/15/25/30/35% across the statutory thresholds.
     */
    private function progressiveTax(float $pkp): float
    {
        // [bracket width, rate] — 0-60jt, 60-250jt, 250-500jt, 500jt-5M, >5M.
        $brackets = [
            [60_000_000, 0.05],
            [190_000_000, 0.15],
            [250_000_000, 0.25],
            [4_500_000_000, 0.30],
            [PHP_INT_MAX, 0.35],
        ];

        $tax = 0.0;
        $remaining = $pkp;

        foreach ($brackets as [$width, $rate]) {
            if ($remaining <= 0) {
                break;
            }

            $slice = min($remaining, (float) $width);
            $tax += $slice * $rate;
            $remaining -= $slice;
        }

        return round($tax);
    }

    /**
     * Push a component's resolved amount into the earnings or deductions bucket.
     * Earnings are tagged proratable so mid-period joiners/leavers can be scaled.
     *
     * @param  list<array{name: string, amount: float, proratable: bool}>  $earnings
     * @param  list<array{name: string, amount: float}>  $deductions
     */
    private function collectComponent(PayrollComponent $component, float $amount, bool $proratable, array &$earnings, array &$deductions, float &$basic): void
    {
        if ($component->type === 'deduction') {
            $deductions[] = ['name' => (string) $component->name, 'amount' => $amount];

            return;
        }

        $earnings[] = ['name' => (string) $component->name, 'amount' => $amount, 'proratable' => $proratable];

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
