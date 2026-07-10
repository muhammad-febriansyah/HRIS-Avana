<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\PayrollPeriodResource;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\BpjsProgram;
use App\Models\CashAdvance;
use App\Models\DayCalcMethod;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentValue;
use App\Models\PayrollCorrection;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PkpRate;
use App\Models\PtkpRate;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use App\Models\SalaryRapel;
use App\Models\TaxProfile;
use App\Models\UmrRate;
use App\Support\Pph21Ter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                'period_id' => $latestPeriod?->id,
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
        // Record the runner so approval can enforce segregation of duties. A
        // recompute resets approval, so the latest runner is the accountable one.
        $run->run_by = $request->user()->id;
        $run->approved_by = null;
        $run->approved_at = null;
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

            // THR is based on a full month's wage, never the prorated payslip.
            $base = $this->monthlyBaseWage($employee, $tenantId);

            if ($base <= 0) {
                $pay = $this->computeEmployeePay($employee, $basePeriod ?? $period, $tenantId);
                $base = $pay['basic'] > 0 ? $pay['basic'] : $pay['gross'];
            }

            // Permenaker 6/2016: eligibility requires at least one continuous
            // month of service; below that the entitlement is zero (row kept
            // for a complete run register).
            $factor = $monthsWorked >= 1 ? min(1.0, $monthsWorked / 12) : 0.0;
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
    public function transferFile(Request $request): StreamedResponse|RedirectResponse
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

        // Disbursement is only allowed on finalized (locked) figures.
        if ($run->status !== 'locked') {
            return back()->withErrors(['payroll' => 'Kunci periode terlebih dahulu sebelum membuat file transfer bank.']);
        }

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
    public function bpjsFile(Request $request): StreamedResponse|RedirectResponse
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $run = PayrollRun::forTenant($tenantId)
            ->whereHas('period', fn ($query) => $query->where('code', 'not like', 'THR-%'))
            ->orderByDesc('id')
            ->with(['period', 'items.employee'])
            ->first();

        abort_if($run === null, 404);

        // BPJS reporting is filed from finalized (locked) figures.
        if ($run->status !== 'locked') {
            return back()->withErrors(['payroll' => 'Kunci periode terlebih dahulu sebelum ekspor BPJS.']);
        }

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
        $ptkp = $this->ptkpFor($ptkpStatus, (int) $employee->tenant_id, $year);
        $pkp = max(0.0, floor(($annualGross - $biayaJabatan - $ptkp) / 1000) * 1000);
        $annualTax = $this->progressiveTax($pkp, (int) $employee->tenant_id, $year);

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

        abort_if($run === null, 404);

        // BR-11.3: payroll cannot be finalized before it is reviewed & approved.
        if ($run->status !== 'approved' && $run->status !== 'locked') {
            return back()->withErrors(['payroll' => 'Payroll harus direview & disetujui sebelum dikunci.']);
        }

        // Advancing installments + flipping status must be atomic so a mid-way
        // failure cannot leave installments bumped without the run being locked.
        DB::transaction(function () use ($run, $period, $tenantId): void {
            // Finalizing advances loan/cash-advance installments exactly once.
            if ($run->status !== 'locked') {
                $this->advanceInstallments($run, $tenantId);
                $run->update(['status' => 'locked']);
            }

            $period->update(['status' => 'locked']);
        });

        return back()->with('success', 'Payroll dikunci');
    }

    /**
     * Authorized unlock of a finalized period (UAT: "data payroll tidak berubah
     * tanpa proses unlock/adjustment berotorisasi"). Reverses the installment
     * advances made at lock time, reopens the period for recompute/re-lock, and
     * records the actor + reason to the audit trail. Requires an explicit period
     * id because a locked period is never the implicit "target" period.
     */
    public function unlock(Request $request): RedirectResponse
    {
        $this->authorize('run', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'payroll_period_id' => [
                'required', 'integer',
                Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $period = PayrollPeriod::forTenant($tenantId)->findOrFail($data['payroll_period_id']);

        if ($period->status !== 'locked') {
            return back()->withErrors(['payroll' => 'Periode belum terkunci, tidak perlu dibuka.']);
        }

        $run = PayrollRun::forTenant($tenantId)
            ->where('payroll_period_id', $period->id)
            ->orderByDesc('id')
            ->first();

        abort_if($run === null, 404);

        DB::transaction(function () use ($run, $period, $tenantId, $request, $data): void {
            // Reverse the installment advances made when the period was locked so
            // reopening does not leave loans/advances over-counted.
            if ($run->status === 'locked') {
                $this->reverseInstallments($run, $tenantId);
                $run->update(['status' => 'approved']);
            }

            // Reopen the period so it can be recomputed or re-locked.
            $period->update(['status' => 'draft']);

            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $request->user()->id,
                'auditable_type' => $run->getMorphClass(),
                'auditable_id' => $run->getKey(),
                'action' => 'payroll_unlocked',
                'old_values' => ['status' => 'locked'],
                'new_values' => ['status' => 'draft', 'period_code' => $period->code, 'reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('success', 'Periode dibuka kembali');
    }

    /**
     * Mark the latest calculated run as reviewed & approved (BR-11.3), recording
     * the approver. Recomputing a run resets it to 'calculated', requiring
     * re-approval before it can be locked.
     */
    public function approve(Request $request): RedirectResponse
    {
        $this->authorize('run', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $period = $this->resolveTargetPeriod($tenantId);

        abort_if($period === null, 404);

        $run = PayrollRun::forTenant($tenantId)
            ->where('payroll_period_id', $period->id)
            ->orderByDesc('id')
            ->first();

        abort_if($run === null, 404);

        if ($run->status === 'locked') {
            return back()->withErrors(['payroll' => 'Payroll sudah dikunci.']);
        }

        if ($run->status !== 'calculated' && $run->status !== 'approved') {
            return back()->withErrors(['payroll' => 'Hitung payroll terlebih dahulu.']);
        }

        // Segregation of duties: when the tenant requires it, the person who ran
        // the payroll may not approve their own run.
        if (
            $request->user()->tenant?->enforce_payroll_segregation
            && $run->run_by !== null
            && (int) $run->run_by === (int) $request->user()->id
        ) {
            return back()->withErrors(['payroll' => 'Pemroses payroll tidak boleh menyetujui hasilnya sendiri (segregation of duties). Minta pengguna lain untuk menyetujui.']);
        }

        $run->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Payroll disetujui');
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
     * Reverse {@see advanceInstallments} when a period is unlocked: step each
     * loan/cash-advance deducted in this run back by one installment and reopen
     * it if the reversal drops it below fully-paid.
     */
    private function reverseInstallments(PayrollRun $run, int $tenantId): void
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
            $paid = max(0, (int) $loan->paid_installments - 1);
            $loan->paid_installments = $paid;

            // A loan settled by this run reverts to the approved (still-deducting) state.
            if ($paid < (int) $loan->tenor_months && $loan->status === 'paid') {
                $loan->status = 'approved';
            }

            $loan->save();
        }

        foreach (CashAdvance::forTenant($tenantId)->whereIn('id', array_unique($advanceIds))->get() as $advance) {
            $paid = max(0, (int) $advance->paid_installments - 1);
            $advance->paid_installments = $paid;

            if ($paid < (int) $advance->installments && $advance->status === 'paid') {
                $advance->status = 'approved';
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
        // Master Gaji (BPR reference) settings drive the attendance/overtime
        // windows, the day divisor and the overtime method for its employees.
        $master = $employee->salary_master_id !== null
            ? SalaryMaster::forTenant($tenantId)->find($employee->salary_master_id)
            : null;

        $presentDays = 0;
        $overtimeRecords = collect();
        $attendanceRange = null;

        if ($period->start_date !== null && $period->end_date !== null) {
            $attendanceRange = $master?->attendance_start_day !== null && $master?->attendance_end_day !== null
                ? $this->masterDateRange($period, $master->attendance_start_day, $master->attendance_end_day, $master->attendance_period)
                : [$period->start_date->toDateString(), $period->end_date->toDateString()];

            $overtimeRange = $master?->overtime_start_day !== null && $master?->overtime_end_day !== null
                ? $this->masterDateRange($period, $master->overtime_start_day, $master->overtime_end_day, $master->overtime_period)
                : $attendanceRange;

            $presentDays = Attendance::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->whereBetween('date', $attendanceRange)
                ->where('status', 'present')
                ->count();

            $overtimeRecords = OvertimeRequest::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', $overtimeRange)
                ->get(['date', 'hours']);
        }

        $overtimeHours = (float) $overtimeRecords->sum('hours');

        // Proration factor from the master's Jumlah Hari divisor + Perhitungan
        // Hari method — applied to that master's prorate-flagged components.
        $masterProrateFactor = $this->masterProrationFactor($master, $presentDays, $attendanceRange);

        // Perhitungan Overtime: "Reguler" pays statutory (Kepmenaker) overtime, so
        // flat per-hour overtime components are suppressed to avoid double pay;
        // "Flat" (or no master) keeps the per-hour component as the overtime line.
        $suppressFlatOvertime = $master?->overtime_calc_method === 'reguler';

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

        // Component ids already paid, so a Master Gaji checklist does not
        // double-count a component already sourced from salary/position rows.
        $handledComponentIds = [];

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component === null) {
                continue;
            }

            $handledComponentIds[$component->id] = true;

            if ($component->basis_type !== null) {
                [$amount, $proratable] = $this->derivedComponentAmount($component, $employee, (float) $salaryComponent->amount, $presentDays, $overtimeHours, $tenantId);
                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic);
            } else {
                $this->collectComponent($component, (float) $salaryComponent->amount, true, $earnings, $deductions, $basic);
            }
        }

        // Master Gaji (BPR manual 1.2.1): pay every component checked into the
        // employee's assigned salary template that a per-employee salary row has
        // not already overridden. Each component's amount is the template's own
        // nominal (falling back to its dasar perhitungan / Nilai Komponen
        // mapping); the template's prorate flag governs mid-period scaling.
        if ($master !== null) {
            $masterComponents = SalaryMasterComponent::query()
                ->where('salary_master_id', $master->id)
                ->where('included', true)
                ->with('component')
                ->get();

            foreach ($masterComponents as $masterComponent) {
                $component = $masterComponent->component;

                if ($component === null || isset($handledComponentIds[$component->id])) {
                    continue;
                }

                if ($suppressFlatOvertime && $component->calc_basis === 'per_overtime_hour') {
                    continue;
                }

                $handledComponentIds[$component->id] = true;

                if ($component->calc_basis === 'per_overtime_hour') {
                    $hasCustomOvertime = true;
                }

                if ($component->basis_type !== null) {
                    [$amount] = $this->derivedComponentAmount($component, $employee, (float) $masterComponent->amount, $presentDays, $overtimeHours, $tenantId);
                } else {
                    // The template's own nominal is the primary source; fall back
                    // to the dimension-mapped Nilai Komponen when it is unset.
                    $base = (float) $masterComponent->amount;

                    if ($base <= 0.0) {
                        $base = $this->resolveComponentValue($component, $employee, $tenantId) ?? 0.0;
                    }

                    $amount = $this->amountForBasis($base, $component->calc_basis, $presentDays, $overtimeHours);
                }

                // is_prorate scales the amount now by the master's Jumlah Hari /
                // Perhitungan Hari factor; otherwise a fixed monthly component is
                // still prorated by the mid-period (join/resign) factor below, and
                // per-day/per-hour components are already scaled by their count.
                $proratable = ! $masterComponent->is_prorate
                    && ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true);

                if ($masterComponent->is_prorate && $masterProrateFactor !== null) {
                    $amount = round($amount * $masterProrateFactor);
                    $proratable = false;
                }

                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic);
            }
        }

        // Approved manual pay corrections (Koreksi Gaji) dated in this period —
        // added as flat, non-prorated earning/deduction lines.
        if ($period->start_date !== null && $period->end_date !== null) {
            $corrections = PayrollCorrection::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('correction_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->get();

            foreach ($corrections as $correction) {
                $name = 'Koreksi: '.$correction->reason;
                $amount = (float) $correction->amount;

                if ($correction->type === 'deduction') {
                    $deductions[] = ['name' => $name, 'amount' => $amount];
                } else {
                    $earnings[] = ['name' => $name, 'amount' => $amount, 'proratable' => false, 'taxable' => false];
                }
            }
        }

        // Rapel (retroactive salary adjustment): the monthly (new − old) nominal
        // difference back-paid for every whole month elapsed since it took effect
        // (UAT: selisih gaji masa lalu = komponen adjustment periode berjalan).
        // Posted once, in the period whose window contains its posting date, and
        // taxable as salary; a negative difference lands as a deduction.
        if ($period->start_date !== null && $period->end_date !== null) {
            $rapels = SalaryRapel::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('posting_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
                ->with('component:id,name')
                ->get();

            foreach ($rapels as $rapel) {
                $effectiveStart = $rapel->effective_from?->copy()->startOfMonth();
                $periodStart = $period->start_date->copy()->startOfMonth();

                // Whole months of back-pay owed before the current period.
                $months = $effectiveStart !== null && $effectiveStart->lt($periodStart)
                    ? $effectiveStart->diffInMonths($periodStart)
                    : 0;

                if ($months <= 0) {
                    continue;
                }

                $total = round(((float) $rapel->new_amount - (float) $rapel->old_amount) * $months);

                if ($total === 0.0) {
                    continue;
                }

                $label = 'Rapel: '.($rapel->component?->name ?? $rapel->label ?? 'Gaji');

                if ($total < 0) {
                    $deductions[] = ['name' => $label, 'amount' => abs($total)];
                } else {
                    $earnings[] = ['name' => $label, 'amount' => $total, 'proratable' => false, 'taxable' => true];
                }
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
                $earnings[] = ['name' => 'Lembur', 'amount' => $overtimePay, 'proratable' => false, 'taxable' => true];
            }
        }

        $gross = (float) array_sum(array_column($earnings, 'amount'));

        // Taxable base = only earnings flagged taxable (manual "Perhitungan Pajak").
        $taxableGross = (float) array_sum(array_map(
            static fn (array $row): float => ($row['taxable'] ?? true) ? (float) $row['amount'] : 0.0,
            $earnings,
        ));

        // Recurring loan & cash-advance installments deducted from take-home pay.
        [$recurring, $loanIds, $advanceIds] = $this->recurringDeductions($employee, $tenantId);

        foreach ($recurring as $line) {
            $deductions[] = $line;
        }

        // Statutory deductions computed from internal config (no external API).
        $bpjs = $this->computeBpjs($employee, $tenantId, $basic > 0 ? $basic : $gross);

        // December (or the employee's final tax month) reconciles the year against
        // the progressive Pasal 17 tariff; other months use the monthly progressive
        // method. Both work on the taxable base, not the full gross.
        $pph21 = $this->isFinalTaxMonth($employee, $period)
            ? $this->computeAnnualPph21($employee, $tenantId, $period, $taxableGross)
            : $this->computePph21($employee, $tenantId, $taxableGross);

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
        $handledComponentIds = [];

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->with('component')
            ->get();

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component !== null && $component->type !== 'deduction') {
                $handledComponentIds[$component->id] = true;
                $total += (float) $salaryComponent->amount;
            }
        }

        if ($employee->salary_master_id !== null) {
            $masterComponents = SalaryMasterComponent::query()
                ->where('salary_master_id', $employee->salary_master_id)
                ->where('included', true)
                ->with('component')
                ->get();

            foreach ($masterComponents as $masterComponent) {
                $component = $masterComponent->component;

                if ($component !== null
                    && ! isset($handledComponentIds[$component->id])
                    && $component->type !== 'deduction'
                    && ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true)) {
                    $total += (float) $masterComponent->amount;
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
     * Resolve a Master Gaji periode range (e.g. "25 s.d 24") into concrete
     * dates for the payroll month. The end day sits in the payroll month; a
     * start day greater than the end day opens the window in the previous
     * month. "Bulan Lalu" shifts the whole window back one month.
     *
     * @return array{0: string, 1: string}
     */
    private function masterDateRange(PayrollPeriod $period, int $startDay, int $endDay, ?string $mode): array
    {
        $anchor = ($period->end_date ?? $period->start_date ?? Carbon::now())->copy();

        $end = $anchor->copy()->day(min($endDay, $anchor->daysInMonth));

        if ($startDay > $endDay) {
            $prev = $anchor->copy()->subMonthNoOverflow();
            $start = $prev->day(min($startDay, $prev->daysInMonth));
        } else {
            $start = $anchor->copy()->day(min($startDay, $anchor->daysInMonth));
        }

        if ($mode === 'bulan_lalu') {
            // Reassign — the app's date casts are immutable, so bare mutators no-op.
            $start = $start->subMonthNoOverflow();
            $end = $end->subMonthNoOverflow();
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    /**
     * Proration factor from a Master Gaji's Jumlah Hari divisor and Perhitungan
     * Hari method: worked days (present count, working days or calendar days in
     * the window) over the divisor, capped at 1. Null when no master/divisor.
     */
    private function masterProrationFactor(?SalaryMaster $master, int $presentDays, ?array $range): ?float
    {
        if ($master === null) {
            return null;
        }

        // A referenced "Perhitungan Hari" method (Setting Komponen master) wins
        // over the master's inline day_calc_method/day_divisor when assigned.
        $method = $master->day_calc_method_id !== null
            ? DayCalcMethod::forTenant($master->tenant_id)->find($master->day_calc_method_id)
            : null;

        $basis = $method->basis ?? $master->day_calc_method;
        $divisor = $method?->divisor ?? $master->day_divisor;

        if (! $divisor) {
            return null;
        }

        $worked = match ($basis) {
            'hari_kalender' => $range !== null ? Carbon::parse($range[0])->diffInDays(Carbon::parse($range[1])) + 1 : $presentDays,
            'hari_kerja' => $range !== null ? $this->weekdaysBetween($range[0], $range[1]) : $presentDays,
            default => $presentDays, // 'absen', 'formula' or unset
        };

        return min(1.0, $worked / $divisor);
    }

    /**
     * Count Mon–Fri days in an inclusive date range.
     */
    private function weekdaysBetween(string $start, string $end): int
    {
        $cursor = Carbon::parse($start);
        $last = Carbon::parse($end);
        $count = 0;

        while ($cursor->lte($last)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }

            $cursor = $cursor->addDay();
        }

        return $count;
    }

    /**
     * Resolve a component amount from its "Dasar Perhitungan" (BPR manual 1.2.2)
     * when a basis_type is configured. Returns [amount, proratable].
     *
     *   - fixed   : basis_value as the per-unit amount x attendance calc_basis
     *   - tabel   : the Nilai Komponen mapping value (fallback to the attachment
     *               amount) x attendance calc_basis
     *   - formula : the evaluated Master Formula (already a full value; not
     *               re-multiplied by the attendance basis, not outer-prorated)
     *
     * @return array{0: float, 1: bool}
     */
    private function derivedComponentAmount(PayrollComponent $component, Employee $employee, float $attachmentAmount, int $presentDays, float $overtimeHours, int $tenantId): array
    {
        $attendanceBasis = ['per_present_day', 'per_overtime_hour'];

        switch ($component->basis_type) {
            case 'fixed':
                $amount = $this->amountForBasis((float) $component->basis_value, $component->calc_basis, $presentDays, $overtimeHours);

                return [$amount, ! in_array($component->calc_basis, $attendanceBasis, true)];

            case 'tabel':
                $base = $this->resolveComponentValue($component, $employee, $tenantId) ?? $attachmentAmount;
                $amount = $this->amountForBasis($base, $component->calc_basis, $presentDays, $overtimeHours);

                return [$amount, ! in_array($component->calc_basis, $attendanceBasis, true)];

            case 'formula':
                return [$this->evaluateComponentFormula($component, $employee, $tenantId), false];

            default:
                $amount = $this->amountForBasis($attachmentAmount, $component->calc_basis, $presentDays, $overtimeHours);

                return [$amount, ! in_array($component->calc_basis, $attendanceBasis, true)];
        }
    }

    /**
     * Resolve the "Nilai Komponen" mapping value (BPR manual 1.2.4) for an
     * employee: the most-specific row whose set dimensions all match. A row that
     * sets a dimension the employee does not match is excluded; among the rest,
     * the row constraining the most dimensions wins (newest breaks ties).
     */
    private function resolveComponentValue(PayrollComponent $component, Employee $employee, int $tenantId): ?float
    {
        $rows = PayrollComponentValue::forTenant($tenantId)
            ->where('payroll_component_id', $component->id)
            ->get();

        $best = null;
        $bestScore = -1;

        foreach ($rows as $row) {
            $dimensions = [
                [$row->kategori, $employee->kategori],
                [$row->employment_status, $employee->employment_status],
                [$row->position_id, $employee->position_id],
                [$row->job_level_id, $employee->job_level_id],
                [$row->branch_id, $employee->branch_id],
            ];

            $score = 0;
            $matches = true;

            foreach ($dimensions as [$constraint, $actual]) {
                if ($constraint === null || $constraint === '') {
                    continue;
                }

                if ((string) $constraint !== (string) $actual) {
                    $matches = false;

                    break;
                }

                $score++;
            }

            if ($matches && ($score > $bestScore || ($score === $bestScore && $best !== null && $row->id > $best->id))) {
                $best = $row;
                $bestScore = $score;
            }
        }

        return $best !== null ? (float) $best->value : null;
    }

    /**
     * Evaluate a component's Master Formula (BPR manual kombinasi komponen): each
     * item contributes operand x nilai, summed, then clamped to the component's
     * min/max. The operand is the referenced component's mapped/attachment value,
     * or the employee's monthly base wage for a UMR item.
     */
    private function evaluateComponentFormula(PayrollComponent $component, Employee $employee, int $tenantId): float
    {
        $formula = $component->relationLoaded('formula') && $component->formula !== null
            ? $component->formula->loadMissing('items.component')
            : $component->formula()->with('items.component')->first();

        if ($formula === null) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($formula->items as $item) {
            $operand = $item->tipe === 'umr'
                ? $this->resolveUmr($employee, $tenantId)
                : $this->componentOperandValue($item->component, $employee, $tenantId);

            $total += $operand * (float) $item->nilai;
        }

        if ($component->basis_min !== null) {
            $total = max((float) $component->basis_min, $total);
        }

        if ($component->basis_max !== null) {
            $total = min((float) $component->basis_max, $total);
        }

        return round($total);
    }

    /**
     * Resolve a formula operand component's value for an employee without
     * recursing into its own basis: its Nilai Komponen mapping, then its
     * position/salary attachment amount, then a fixed basis value.
     */
    private function componentOperandValue(?PayrollComponent $component, Employee $employee, int $tenantId): float
    {
        if ($component === null) {
            return 0.0;
        }

        $mapped = $this->resolveComponentValue($component, $employee, $tenantId);

        if ($mapped !== null) {
            return $mapped;
        }

        $salaryAmount = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('payroll_component_id', $component->id)
            ->value('amount');

        if ($salaryAmount !== null) {
            return (float) $salaryAmount;
        }

        if ($employee->salary_master_id !== null) {
            $masterAmount = SalaryMasterComponent::query()
                ->where('salary_master_id', $employee->salary_master_id)
                ->where('payroll_component_id', $component->id)
                ->where('included', true)
                ->value('amount');

            if ($masterAmount !== null && (float) $masterAmount > 0.0) {
                return (float) $masterAmount;
            }
        }

        return $component->basis_type === 'fixed' ? (float) $component->basis_value : 0.0;
    }

    /**
     * Resolve the UMR (regional minimum wage) for an employee: the newest rate
     * up to the current year for the employee's branch, then the tenant-wide
     * default (null branch). Falls back to the full monthly wage when no UMR is
     * configured, so a `umr` formula item still yields a value.
     */
    private function resolveUmr(Employee $employee, int $tenantId): float
    {
        $rate = UmrRate::forTenant($tenantId)
            ->where('year', '<=', (int) now()->year)
            ->where(fn ($q) => $q->where('branch_id', $employee->branch_id)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NULL') // branch-specific rate wins over the default
            ->orderByDesc('year')
            ->value('amount');

        return $rate !== null ? (float) $rate : $this->monthlyBaseWage($employee, $tenantId);
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
     * Compute the monthly PPh 21 withholding using the TER scheme (PP 58/2023 &
     * PMK 168/2023): a flat effective rate — looked up by the employee's TER
     * category (from PTKP status) and monthly taxable gross — applied to that
     * gross. December / final-month reconciliation against the progressive
     * Pasal 17 tariff is handled separately by computeAnnualPph21().
     *
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    private function computePph21(Employee $employee, int $tenantId, float $gross): array
    {
        $profile = TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        $status = $profile?->ptkp_status;
        $category = Pph21Ter::category($status);
        $rate = Pph21Ter::monthlyRate($category, $gross);
        $amount = round($gross * $rate);

        return [
            'amount' => $amount,
            'snapshot' => [
                'method' => 'ter',
                'ptkp_status' => $status,
                'ter_category' => $category,
                'ter_rate' => $rate,
                'gross' => round($gross),
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
        $ptkp = $this->ptkpFor($profile?->ptkp_status, $tenantId, $year);

        $pkp = max(0.0, $annualGross - $biayaJabatan - $ptkp);
        $pkp = floor($pkp / 1000) * 1000; // taxable income is floored to thousands

        $annualTax = $this->progressiveTax($pkp, $tenantId, $year);
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
     * Resolve the annual PTKP allowance for a status code (TK/0, K/3, ...).
     * Reads the tenant's configurable Tarif PTKP table, falling back to the
     * statutory computation (base 54jt, +4,5jt married, +4,5jt per dependant).
     */
    private function ptkpFor(?string $status, int $tenantId, int $year): float
    {
        $status = $status !== null && $status !== '' ? strtoupper(trim($status)) : 'TK/0';

        $configured = PtkpRate::forTenant($tenantId)
            ->where('ptkp_status', $status)
            ->where('year', $year)
            ->value('amount');

        if ($configured !== null) {
            return (float) $configured;
        }

        $base = 54_000_000.0;
        $married = str_starts_with($status, 'K');
        $dependents = preg_match('/(\d+)/', $status, $matches) ? min((int) $matches[1], 3) : 0;

        return $base + ($married ? 4_500_000 : 0) + 4_500_000 * $dependents;
    }

    /**
     * Progressive annual income tax on taxable income (PKP). Reads the tenant's
     * configurable Tarif PKP brackets, falling back to the UU HPP Pasal 17
     * brackets (5/15/25/30/35%).
     */
    private function progressiveTax(float $pkp, int $tenantId, int $year): float
    {
        $configured = PkpRate::forTenant($tenantId)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderBy('up_to')
            ->get(['up_to', 'rate']);

        // [cumulative upper bound (null = infinity), rate].
        $brackets = $configured->isNotEmpty()
            ? $configured->map(fn (PkpRate $r): array => [
                $r->up_to !== null ? (float) $r->up_to : null,
                (float) $r->rate,
            ])->all()
            : [
                [60_000_000.0, 0.05],
                [250_000_000.0, 0.15],
                [500_000_000.0, 0.25],
                [5_000_000_000.0, 0.30],
                [null, 0.35],
            ];

        $tax = 0.0;
        $lower = 0.0;

        foreach ($brackets as [$upper, $rate]) {
            if ($pkp <= $lower) {
                break;
            }

            $ceiling = $upper ?? $pkp;
            $slice = min($pkp, $ceiling) - $lower;

            if ($slice > 0) {
                $tax += $slice * $rate;
            }

            $lower = $ceiling;
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
        $isDeduction = $component->type === 'deduction' || $component->component_group === 'potongan';

        if ($isDeduction) {
            $deductions[] = ['name' => (string) $component->name, 'amount' => $amount];

            return;
        }

        // The `is_taxable` flag mirrors the manual's "Perhitungan Pajak Ya/Tidak":
        // only taxable earnings enter the PPh 21 base.
        $earnings[] = [
            'name' => (string) $component->name,
            'amount' => $amount,
            'proratable' => $proratable,
            'taxable' => (bool) $component->is_taxable,
        ];

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
