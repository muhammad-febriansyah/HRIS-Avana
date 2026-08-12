<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\PayrollPeriodResource;
use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AuditLog;
use App\Models\BpjsProgram;
use App\Models\DayCalcMethod;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\Payday;
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
use App\Services\SalaryMasterAssignment;
use App\Support\AttendanceFines;
use App\Support\OvertimeRules;
use App\Support\Pph21Calculator;
use App\Support\Pph21Ter;
use App\Support\TaxForm1721;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
     * Company-paid premiums that are the employee's income for PPh 21.
     *
     * JKK, JKM and BPJS Kesehatan are a benefit the employee receives now, so
     * they belong in the month's bruto the TER rate is applied to. JHT and JP
     * are deliberately absent: those are deferred, and taxed when the employee
     * draws them, not when the company pays them in.
     *
     * @var array<int, string>
     */
    private const TAXABLE_COMPANY_PREMIUMS = ['jkk', 'jkm', 'kesehatan'];

    /**
     * Employee-paid premiums that come off gross in the annual reconciliation.
     *
     * The employee's own JHT and JP are pension-type contributions and reduce
     * the year's taxable income. They do NOT reduce the monthly TER base — TER
     * is applied to bruto by definition.
     *
     * @var array<int, string>
     */
    private const DEDUCTIBLE_EMPLOYEE_PREMIUMS = ['jht', 'jp'];

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

        // The period whose payslips are shown — a chosen one (?period) or the
        // latest. This lets HR open the recipient list for any month, not just
        // the current draft.
        $selectedPeriod = $request->filled('period')
            ? PayrollPeriod::forTenant($tenantId)
                ->with(['runs' => fn ($query) => $query->orderByDesc('id')])
                ->find($request->integer('period'))
            : null;

        $selectedPeriod ??= PayrollPeriod::forTenant($tenantId)
            ->with(['runs' => fn ($query) => $query->orderByDesc('id')])
            ->orderByDesc('start_date')
            ->first();

        $selectedRun = $selectedPeriod?->runs->first();

        // Recipient list is server-side paginated + filterable so a period with
        // hundreds of employees stays a small payload. HR can narrow by name/NIP,
        // tax scheme, or hide the not-yet-paid (net 0) rows.
        $search = trim((string) $request->input('search', ''));
        $scheme = trim((string) $request->input('scheme', ''));
        $onlyPaid = $request->boolean('only_paid');
        $perPage = min(max((int) $request->input('per_page', 20), 10), 100);

        $recipientsPage = $selectedRun === null ? null : PayrollRunItem::where('payroll_run_id', $selectedRun->id)
            ->with('employee:id,full_name,employee_number')
            ->when($search !== '', fn ($query) => $query->whereHas(
                'employee',
                fn ($sub) => $sub->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%"),
            ))
            ->when($scheme !== '', fn ($query) => $query->where('calculation_snapshot->tax->method', $scheme))
            ->when($onlyPaid, fn ($query) => $query->where('net_salary', '>', 0))
            ->orderByDesc('net_salary')
            ->paginate($perPage)
            ->withQueryString();

        $recipients = $recipientsPage === null ? [] : collect($recipientsPage->items())
            ->map(function (PayrollRunItem $item): array {
                $snapshot = is_array($item->calculation_snapshot) ? $item->calculation_snapshot : [];

                return [
                    'id' => $item->id,
                    'route_key' => $item->public_id,
                    'employee_id' => $item->employee_id,
                    'name' => $item->employee?->full_name ?? '—',
                    'employee_number' => $item->employee?->employee_number,
                    'gross' => $this->rupiah($item->gross_salary),
                    'deduction' => $this->rupiah($item->total_deduction),
                    'tax' => $this->rupiah($item->pph21_total),
                    'net' => $this->rupiah($item->net_salary),
                    'tax_method' => $snapshot['tax']['method'] ?? null,
                ];
            })
            ->all();

        $recipientMeta = $recipientsPage === null ? null : [
            'current_page' => $recipientsPage->currentPage(),
            'last_page' => $recipientsPage->lastPage(),
            'per_page' => $recipientsPage->perPage(),
            'from' => $recipientsPage->firstItem(),
            'to' => $recipientsPage->lastItem(),
            'total' => $recipientsPage->total(),
        ];

        return Inertia::render('avana/payroll/index', [
            'periods' => PayrollPeriodResource::collection($periods),
            'summary' => [
                'period' => $selectedPeriod?->name,
                'period_id' => $selectedPeriod?->id,
                'pay_date' => $selectedPeriod?->pay_date?->toDateString(),
                'start_date' => $selectedPeriod?->start_date?->toDateString(),
                'end_date' => $selectedPeriod?->end_date?->toDateString(),
                'status' => $selectedRun?->status ?? $selectedPeriod?->status,
                'status_label' => PayrollPeriodResource::statusLabel($selectedRun?->status ?? $selectedPeriod?->status),
                'approval_note' => $selectedRun?->approval_note,
                'rejection_note' => $selectedRun?->rejection_note,
                'total_gross' => $this->rupiah($selectedRun?->total_gross ?? 0),
                'total_deduction' => $this->rupiah($selectedRun?->total_deduction ?? 0),
                'total_tax' => $this->rupiah($selectedRun?->total_tax ?? 0),
                'total_net' => $this->rupiah($selectedRun?->total_net ?? 0),
                'employee_count' => (int) ($selectedRun?->employee_count ?? 0),
                'recipient_count' => $recipientMeta['total'] ?? 0,
            ],
            'recipients' => $recipients,
            'recipient_meta' => $recipientMeta,
            'slip' => $this->buildSampleSlip($tenantId, $selectedPeriod, $request->integer('slip_employee') ?: null),
            'slip_employees' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->full_name])
                ->all(),
            'stale_run' => $this->runIsStale($tenantId, $selectedRun),
            'checklist' => $this->setupChecklist($tenantId),
            'filters' => $request->only(['search', 'status', 'per_page', 'period', 'scheme', 'only_paid', 'slip_employee']),
        ]);
    }

    /**
     * The setup steps, in the order the payroll documentation walks them, each
     * marked done from the tenant's own data. A new tenant sees where they are
     * and what is still missing before their first run — the checklist earns
     * its screen space only until every step is green, then disappears.
     *
     * @return list<array{key: string, label: string, done: bool, href: string|null, hint: string|null}>
     */
    private function setupChecklist(int $tenantId): array
    {
        $activeEmployees = Employee::forTenant($tenantId)->where('status', 'active')->count();

        $components = DB::table('payroll_components')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->count();

        $linkedToMaster = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNotNull('salary_master_id')
            ->count();

        $umr = DB::table('umr_rates')->where('tenant_id', $tenantId)->count();
        $grades = DB::table('salary_grades')->where('tenant_id', $tenantId)->count();

        $taxProfiles = DB::table('tax_profiles')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();

        $paydayGroups = DB::table('paydays')->where('tenant_id', $tenantId)->where('is_active', true)->count();
        $paydayAssigned = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->whereNotNull('payday_id')
            ->count();

        $overtimeReady = DB::table('overtime_policies')->where('tenant_id', $tenantId)->exists()
            && DB::table('overtime_rates')->where('tenant_id', $tenantId)->exists();

        $hasRun = PayrollRun::forTenant($tenantId)->exists();

        return [
            [
                'key' => 'komponen',
                'label' => 'Master Komponen',
                'done' => $components > 0,
                'href' => '/avana/payroll/komponen',
                'hint' => $components > 0 ? $components.' komponen aktif' : 'Definisikan pendapatan & potongan',
            ],
            [
                'key' => 'master-gaji',
                'label' => 'Master Gaji',
                'done' => $linkedToMaster > 0,
                'href' => '/avana/payroll/master-gaji',
                'hint' => $linkedToMaster > 0
                    ? $linkedToMaster.' dari '.$activeEmployees.' karyawan tertaut'
                    : 'Belum ada karyawan tertaut template gaji',
            ],
            [
                'key' => 'umr',
                'label' => 'UMR',
                'done' => $umr > 0,
                'href' => '/avana/payroll/umr',
                'hint' => $umr > 0 ? $umr.' wilayah' : 'Isi UMR untuk validasi gaji',
            ],
            [
                'key' => 'struktur-upah',
                'label' => 'Struktur & Skala Upah',
                'done' => $grades > 0,
                'href' => '/avana/struktur-upah',
                'hint' => $grades > 0 ? $grades.' grade' : 'Buat rentang gaji per grade',
            ],
            [
                'key' => 'pajak',
                'label' => 'BPJS & Pajak',
                'done' => $taxProfiles >= $activeEmployees && $activeEmployees > 0,
                'href' => '/avana/payroll/konfigurasi',
                'hint' => $taxProfiles >= $activeEmployees && $activeEmployees > 0
                    ? 'Profil pajak lengkap'
                    : ($activeEmployees - $taxProfiles).' karyawan tanpa profil pajak — PPh21 memakai TK/0',
            ],
            [
                'key' => 'payday',
                'label' => 'Mapping Payday',
                'done' => $paydayGroups > 0 && $paydayAssigned > 0,
                'href' => '/avana/payroll/payday',
                'hint' => $paydayGroups > 0 && $paydayAssigned > 0
                    ? $paydayAssigned.' karyawan terpetakan'
                    : 'Buat kelompok payday & assign karyawan',
            ],
            [
                'key' => 'lembur',
                'label' => 'Setup Lembur',
                'done' => $overtimeReady,
                'href' => '/avana/payroll/lembur',
                'hint' => $overtimeReady ? 'Aturan & pengali siap' : 'Atur basis, pengali, dan batas jam',
            ],
            [
                'key' => 'run',
                'label' => 'Jalankan Payroll',
                'done' => $hasRun,
                'href' => null,
                'hint' => $hasRun ? 'Sudah pernah dihitung' : 'Klik Jalankan di halaman ini',
            ],
        ];
    }

    /**
     * Whether the shown run was computed before the tenant last touched any
     * payroll configuration — the tester's "I changed the salary but the table
     * still shows the old number" moment. The table is a stored result, not a
     * live view, so the page says so instead of leaving them to guess.
     */
    private function runIsStale(int $tenantId, ?PayrollRun $run): bool
    {
        if ($run === null || in_array($run->status, ['locked'], true)) {
            return false;
        }

        $computedAt = PayrollRunItem::where('payroll_run_id', $run->id)->max('updated_at');

        if ($computedAt === null) {
            return false;
        }

        $changedAt = $this->latestConfigChangeAt($tenantId);

        return $changedAt !== null && $changedAt > $computedAt;
    }

    /**
     * The most recent moment any payroll input this tenant edits was changed:
     * components and their values, salary templates and per-employee rows,
     * overtime policy/multipliers, late-fine tiers, payday groups and BPJS
     * enrolments.
     */
    private function latestConfigChangeAt(int $tenantId): ?string
    {
        $masterIds = DB::table('salary_masters')->where('tenant_id', $tenantId)->pluck('id');

        $stamps = [
            DB::table('payroll_components')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('payroll_component_values')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('salary_masters')->where('tenant_id', $tenantId)->max('updated_at'),
            $masterIds->isEmpty() ? null : DB::table('salary_master_components')->whereIn('salary_master_id', $masterIds)->max('updated_at'),
            DB::table('employee_salary_components')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('overtime_policies')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('overtime_rates')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('attendance_penalty_rules')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('paydays')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('employee_bpjs_profiles')->where('tenant_id', $tenantId)->max('updated_at'),
        ];

        return collect($stamps)->filter()->max();
    }

    /**
     * Create or refresh the payroll run for the current draft period and
     * recompute every active employee's run item.
     */
    public function run(Request $request): RedirectResponse
    {
        $this->authorize('create', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        // The run-confirmation popup lets HR verify/adjust the pay date before
        // executing, since it drives the bank disbursement (BPR manual 1.3.1).
        $data = $request->validate([
            'pay_date' => ['nullable', 'date'],
            'payroll_period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId)],
        ]);

        [$period, $run, $employees, $totals] = DB::transaction(function () use ($tenantId, $data, $request): array {
            $period = $this->targetPeriodFor($request, $tenantId, true);

            abort_if($period === null, 404);

            if ($period->status === 'locked') {
                throw ValidationException::withMessages([
                    'payroll' => 'Periode terkunci, tidak bisa dihitung ulang.',
                ]);
            }

            if (! empty($data['pay_date']) && $data['pay_date'] !== $period->pay_date?->toDateString()) {
                $period->update(['pay_date' => $data['pay_date']]);
            }

            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->whereNull('branch_id')
                ->lockForUpdate()
                ->first() ?? new PayrollRun([
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'branch_id' => null,
                ]);
            $run->fill([
                'status' => 'calculated',
                'run_by' => $request->user()->id,
                'approved_by' => null,
                'approved_at' => null,
                'approval_note' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ])->save();

            $employees = $this->payableEmployees($tenantId, $period);
            $employeeIds = $employees->pluck('id')->all();
            $run->items()->whereNotIn('employee_id', $employeeIds)->delete();

            $totals = ['gross' => 0.0, 'deduction' => 0.0, 'tax' => 0.0, 'net' => 0.0, 'count' => 0];

            foreach ($employees as $employee) {
                $pay = $this->computeEmployeePay($employee, $period, $tenantId);

                PayrollRunItem::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                    [
                        'tenant_id' => $tenantId,
                        'payroll_period_id' => $period->id,
                        'gross_salary' => $pay['gross'],
                        'taxable_gross' => $pay['taxable_gross'],
                        'tax_deductible_premium' => $pay['tax_deductible_premium'],
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
                            'overtime' => $pay['overtime_snapshot'],
                            'payday' => $pay['payday_snapshot'],
                            'salary_sources' => $pay['salary_sources'],
                            'salary_master_id' => $pay['salary_master_id'],
                            'proration_factor' => $pay['proration_factor'],
                            'loan_ids' => $pay['loan_ids'],
                            'gross' => $pay['gross'],
                            'deduction' => $pay['deduction'],
                            'bpjs' => $pay['bpjs_snapshot'],
                            'tax' => $pay['tax_snapshot'],
                            'net' => $pay['net'],
                        ],
                        'status' => 'calculated',
                    ],
                );

                $totals['gross'] += $pay['gross'];
                $totals['deduction'] += $pay['deduction'];
                $totals['tax'] += $pay['pph21'];
                $totals['net'] += $pay['net'];
                $totals['count']++;
            }

            $run->update([
                'total_gross' => $totals['gross'],
                'total_deduction' => $totals['deduction'],
                'total_tax' => $totals['tax'],
                'total_net' => $totals['net'],
                'employee_count' => $totals['count'],
                'status' => 'calculated',
            ]);

            return [$period, $run, $employees, $totals];
        });

        // Anyone without a PTKP status was taxed as TK/0 — the strictest
        // category, and almost certainly not what they are. The run still
        // stands; it just should not go out unnoticed.
        $missing = $this->employeesMissingPtkp($employees, $tenantId);

        if ($missing !== []) {
            return back()->with('success', 'Payroll dihitung')->with(
                'warning',
                count($missing).' karyawan belum punya status PTKP dan dihitung sebagai TK/0: '
                    .implode(', ', array_slice($missing, 0, 5))
                    .(count($missing) > 5 ? ', …' : '')
                    .'. Lengkapi di Konfigurasi Payroll → Profil Pajak.',
            );
        }

        return back()->with('success', 'Payroll dihitung');
    }

    /**
     * Names of the employees in this run whose tax profile carries no PTKP
     * status, and who therefore fell back to TK/0.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, string>
     */
    private function employeesMissingPtkp(iterable $employees, int $tenantId): array
    {
        $statuses = TaxProfile::where('tenant_id', $tenantId)
            ->whereNotNull('ptkp_status')
            ->where('ptkp_status', '!=', '')
            ->pluck('ptkp_status', 'employee_id');

        $missing = [];

        foreach ($employees as $employee) {
            // Only the subjects whose tax actually depends on PTKP — a peserta
            // kegiatan or bukan pegawai is taxed without one.
            if (! Pph21Calculator::needsAnnualReconciliation($this->taxSubjectOf($employee, $tenantId))) {
                continue;
            }

            // Nothing is withheld for an exempt employee, so a missing PTKP
            // status changes no figure and should not stall the run.
            if ($this->isPph21Exempt($employee, $tenantId)) {
                continue;
            }

            if (! isset($statuses[$employee->id])) {
                $missing[] = (string) $employee->full_name;
            }
        }

        return $missing;
    }

    /**
     * Render the standalone "create payroll period" page.
     */
    public function createPeriod(): Response
    {
        $this->authorize('create', PayrollPeriod::class);

        return Inertia::render('avana/payroll/period-create');
    }

    /**
     * Create a new draft payroll period for a given cycle and date range.
     * Weekly/biweekly periods reuse the same engine — pay components keyed on
     * present-days/overtime are counted within the period's date window.
     */
    public function storePeriod(Request $request): RedirectResponse
    {
        $this->authorize('create', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        // `after`, not `after_or_equal`: a tester once made a one-day "monthly"
        // period (25-08 s.d. 25-08) and every prorated salary collapsed to 1/25
        // of itself. A period is a range by definition.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cycle' => ['required', Rule::in(self::CYCLES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'pay_date' => ['nullable', 'date'],
        ], [
            'end_date.after' => 'Periode tidak boleh satu hari — tanggal selesai harus setelah tanggal mulai. Gaji bulanan yang diprorata akan terpotong mengikuti panjang periode.',
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
        $this->authorize('create', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;
        $asOf = now()->startOfDay();

        // Compute every employee's monthly bruto against the latest regular
        // (non-THR) period; fall back to the THR period when none exists.
        $basePeriod = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->orderByDesc('start_date')
            ->first();

        $year = (int) $asOf->year;

        [$totalThr, $totalThrTax] = DB::transaction(function () use ($tenantId, $year, $asOf, $basePeriod, $request): array {
            DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();

            $period = PayrollPeriod::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'THR-'.$year],
                [
                    'name' => 'THR '.$year,
                    'start_date' => $year.'-01-01',
                    'end_date' => $year.'-12-31',
                    'pay_date' => $asOf->toDateString(),
                    'status' => 'draft',
                ],
            );
            $period = PayrollPeriod::forTenant($tenantId)
                ->whereKey($period->id)
                ->lockForUpdate()
                ->firstOrFail();

            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->whereNull('branch_id')
                ->lockForUpdate()
                ->first();

            if ($period->status === 'locked' || $run?->status === 'locked') {
                throw ValidationException::withMessages([
                    'payroll' => 'THR sudah dikunci dan tidak dapat dihitung ulang.',
                ]);
            }

            $period->update([
                'name' => 'THR '.$year,
                'start_date' => $year.'-01-01',
                'end_date' => $year.'-12-31',
                'pay_date' => $asOf->toDateString(),
                'status' => 'draft',
            ]);

            $run ??= new PayrollRun([
                'tenant_id' => $tenantId,
                'payroll_period_id' => $period->id,
                'branch_id' => null,
            ]);
            $run->fill([
                'status' => 'calculated',
                'run_by' => $request->user()->id,
                'approved_by' => null,
                'approved_at' => null,
                'approval_note' => null,
            ])->save();

            $employees = Employee::forTenant($tenantId)
                ->where(function ($query) use ($asOf): void {
                    $query->where('status', 'active')
                        ->orWhere(fn ($sub) => $sub
                            ->whereNotNull('resign_date')
                            ->where('resign_date', '>=', $asOf->copy()->subDays(30)->toDateString()));
                })
                ->get();

            $run->items()->whereNotIn('employee_id', $employees->pluck('id'))->delete();

            $totalThr = 0.0;
            $totalThrTax = 0.0;

            foreach ($employees as $employee) {
                $monthsWorked = $employee->join_date !== null
                    ? (int) floor(abs($employee->join_date->diffInMonths($asOf)))
                    : 12;
                $base = $this->monthlyBaseWage($employee, $tenantId, $asOf);
                $pay = $this->computeEmployeePay($employee, $basePeriod ?? $period, $tenantId);

                if ($base <= 0) {
                    $base = $pay['basic'] > 0 ? $pay['basic'] : $pay['gross'];
                }

                $factor = $monthsWorked >= 1 ? min(1.0, $monthsWorked / 12) : 0.0;
                $thr = round($base * $factor);
                $tax = $this->computeThrPph21($employee, $tenantId, (float) $thr, (float) $pay['taxable_gross'], $period);

                PayrollRunItem::updateOrCreate(
                    ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                    [
                        'tenant_id' => $tenantId,
                        'payroll_period_id' => $period->id,
                        'gross_salary' => $thr,
                        'taxable_gross' => $thr,
                        'tax_deductible_premium' => 0,
                        'total_allowance' => $thr,
                        'total_deduction' => $tax['amount'],
                        'bpjs_employee_total' => 0,
                        'bpjs_company_total' => 0,
                        'pph21_total' => $tax['amount'],
                        'net_salary' => $thr - $tax['amount'],
                        'calculation_snapshot' => [
                            'months_worked' => $monthsWorked,
                            'base' => $base,
                            'factor' => $factor,
                            'thr' => $thr,
                            'formula' => 'THR = base x min(1, months_worked / 12)',
                            'tax' => $tax['snapshot'],
                        ],
                        'status' => 'calculated',
                    ],
                );

                $totalThr += $thr;
                $totalThrTax += $tax['amount'];
            }

            $run->update([
                'total_gross' => $totalThr,
                'total_deduction' => $totalThrTax,
                'total_tax' => $totalThrTax,
                'total_net' => $totalThr - $totalThrTax,
                'employee_count' => $employees->count(),
                'status' => 'calculated',
            ]);

            return [$totalThr, $totalThrTax];
        });

        return back()->with('success', 'THR dihitung — total '.$this->rupiah($totalThr)
            .($totalThrTax > 0 ? ', PPh 21 '.$this->rupiah($totalThrTax) : ''));
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
        $this->authorize('export', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $format = $request->query('bank', 'generic');
        $format = isset(self::BANK_FORMATS[$format]) ? $format : 'generic';
        $header = self::BANK_FORMATS[$format]['header'];

        // An explicit period id disburses that run (e.g. a THR period, which the
        // default latest-regular resolver excludes); otherwise the latest regular
        // run is used.
        $periodId = $request->query('payroll_period_id');

        $run = PayrollRun::forTenant($tenantId)
            ->when(
                $periodId !== null && $periodId !== '',
                fn ($query) => $query->where('payroll_period_id', (int) $periodId),
                fn ($query) => $query->whereHas('period', fn ($q) => $q->where('code', 'not like', 'THR-%')),
            )
            ->orderByDesc('id')
            ->with(['period', 'items.employee.bankAccounts'])
            ->first();

        abort_if($run === null, 404);

        // Disbursement is only allowed on finalized (locked) figures.
        if ($run->status !== 'locked') {
            return back()->withErrors(['payroll' => 'Kunci periode terlebih dahulu sebelum membuat file transfer bank.']);
        }

        $periodCode = $run->period?->code ?? 'run-'.$run->id;
        $note = (str_starts_with($periodCode, 'THR-') ? 'THR ' : 'Gaji ').($run->period?->name ?? $periodCode);
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
        $this->authorize('export', PayrollPeriod::class);

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
     * Stream a single employee's payslip as a PDF.
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

        $pdf = Pdf::loadView('pdf.bukti-potong-1721', TaxForm1721::viewData($employee, $year))
            ->setPaper('a4');

        return $pdf->download('1721-A1-'.$employee->employee_number.'-'.$year.'.pdf');
    }

    /**
     * Lock the latest payroll run/period so figures can no longer be recomputed.
     */
    public function lock(Request $request): RedirectResponse
    {
        $this->authorize('update', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $request->validate([
            'payroll_period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId)],
        ]);

        DB::transaction(function () use ($request, $tenantId): void {
            $period = $this->targetPeriodFor($request, $tenantId, true);
            abort_if($period === null, 404);

            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            abort_if($run === null, 404);

            if (! in_array($run->status, ['approved', 'locked'], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Payroll harus direview & disetujui sebelum dikunci.',
                ]);
            }

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
        $this->authorize('update', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'payroll_period_id' => [
                'required', 'integer',
                Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($tenantId, $request, $data): void {
            $period = PayrollPeriod::forTenant($tenantId)
                ->whereKey($data['payroll_period_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->status !== 'locked') {
                throw ValidationException::withMessages([
                    'payroll' => 'Periode belum terkunci, tidak perlu dibuka.',
                ]);
            }

            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            abort_if($run === null, 404);

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
        $this->authorize('approve', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
            'payroll_period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId)],
        ]);

        DB::transaction(function () use ($request, $tenantId, $data): void {
            $period = $this->targetPeriodFor($request, $tenantId, true);
            abort_if($period === null, 404);

            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            abort_if($run === null, 404);

            if ($run->status === 'locked') {
                throw ValidationException::withMessages(['payroll' => 'Payroll sudah dikunci.']);
            }

            if (! in_array($run->status, ['calculated', 'approved'], true)) {
                throw ValidationException::withMessages(['payroll' => 'Hitung payroll terlebih dahulu.']);
            }

            if (
                $request->user()->tenant?->enforce_payroll_segregation
                && $run->run_by !== null
                && (int) $run->run_by === (int) $request->user()->id
            ) {
                throw ValidationException::withMessages([
                    'payroll' => 'Pemroses payroll tidak boleh menyetujui hasilnya sendiri (segregation of duties). Minta pengguna lain untuk menyetujui.',
                ]);
            }

            $run->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'approval_note' => $data['note'] ?? null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ]);
        });

        return back()->with('success', 'Payroll disetujui');
    }

    /**
     * Reject a calculated/approved run back to "calculated" with a mandatory
     * reason (BPR manual 1.3.1 approval: status can be rejected). The run must be
     * recomputed/re-approved before it can be locked; the reason is surfaced to
     * the processor.
     */
    public function reject(Request $request): RedirectResponse
    {
        $this->authorize('approve', PayrollPeriod::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:255'],
            'payroll_period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId)],
        ]);

        DB::transaction(function () use ($request, $tenantId, $data): void {
            $period = $this->targetPeriodFor($request, $tenantId, true);
            abort_if($period === null, 404);

            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            abort_if($run === null, 404);

            if ($run->status === 'locked') {
                throw ValidationException::withMessages(['payroll' => 'Payroll sudah dikunci, tidak bisa ditolak.']);
            }

            if (! in_array($run->status, ['calculated', 'approved'], true)) {
                throw ValidationException::withMessages(['payroll' => 'Hitung payroll terlebih dahulu.']);
            }

            $run->update([
                'status' => 'calculated',
                'approved_by' => null,
                'approved_at' => null,
                'approval_note' => null,
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
                'rejection_note' => $data['note'],
            ]);
        });

        return back()->with('success', 'Payroll ditolak, dikembalikan untuk diperbaiki');
    }

    /**
     * Advance the installment counters for every loan that was deducted in this
     * run, settling them once fully paid.
     */
    private function advanceInstallments(PayrollRun $run, int $tenantId): void
    {
        foreach (Loan::forTenant($tenantId)->whereIn('id', $this->deductedLoanIds($run, $tenantId))->orderBy('id')->lockForUpdate()->get() as $loan) {
            $paid = min((int) $loan->tenor_months, (int) $loan->paid_installments + 1);
            $loan->paid_installments = $paid;

            if ($paid >= (int) $loan->tenor_months) {
                $loan->status = 'paid';
            }

            $loan->save();
        }
    }

    /**
     * Reverse {@see advanceInstallments} when a period is unlocked: step each
     * loan deducted in this run back by one installment and reopen it if the
     * reversal drops it below fully-paid.
     */
    private function reverseInstallments(PayrollRun $run, int $tenantId): void
    {
        foreach (Loan::forTenant($tenantId)->whereIn('id', $this->deductedLoanIds($run, $tenantId))->orderBy('id')->lockForUpdate()->get() as $loan) {
            $paid = max(0, (int) $loan->paid_installments - 1);
            $loan->paid_installments = $paid;

            // A loan settled by this run reverts to the approved (still-deducting) state.
            if ($paid < (int) $loan->tenor_months && $loan->status === 'paid') {
                $loan->status = 'approved';
            }

            $loan->save();
        }
    }

    /**
     * The distinct loan ids this run's snapshots recorded a deduction for.
     *
     * @return array<int, int>
     */
    private function deductedLoanIds(PayrollRun $run, int $tenantId): array
    {
        $items = PayrollRunItem::forTenant($tenantId)
            ->where('payroll_run_id', $run->id)
            ->get(['calculation_snapshot']);

        $loanIds = [];

        foreach ($items as $item) {
            $snapshot = $item->calculation_snapshot ?? [];

            foreach ((array) ($snapshot['loan_ids'] ?? []) as $id) {
                $loanIds[] = (int) $id;
            }
        }

        return array_values(array_unique($loanIds));
    }

    /**
     * Resolve the period payroll actions should target: the latest draft period,
     * falling back to the most recent period overall.
     */
    private function resolveTargetPeriod(int $tenantId, bool $lock = false): ?PayrollPeriod
    {
        $query = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->where('status', 'draft')
            ->orderByDesc('start_date');

        $period = $query->first();

        if ($period !== null) {
            return $lock ? $period->newQuery()->whereKey($period->id)->lockForUpdate()->first() : $period;
        }

        $query = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->orderByDesc('start_date');

        $period = $query->first();

        return $lock && $period !== null
            ? $period->newQuery()->whereKey($period->id)->lockForUpdate()->first()
            : $period;
    }

    /**
     * The period an action targets: an explicit `payroll_period_id` when given
     * (e.g. a per-row action, or a THR period which the implicit resolver
     * excludes), otherwise the default regular target period.
     */
    private function targetPeriodFor(Request $request, int $tenantId, bool $lock = false): ?PayrollPeriod
    {
        $periodId = $request->input('payroll_period_id');

        if ($periodId !== null && $periodId !== '') {
            $query = PayrollPeriod::forTenant($tenantId)->whereKey((int) $periodId);

            return ($lock ? $query->lockForUpdate() : $query)->first();
        }

        return $this->resolveTargetPeriod($tenantId, $lock);
    }

    /**
     * Build a computed sample payslip for the first active employee, falling
     * back to a representative example when no components are configured.
     *
     * @return array<string, mixed>
     */
    private function buildSampleSlip(int $tenantId, ?PayrollPeriod $period, ?int $employeeId = null): array
    {
        // A chosen employee makes this a dry-run preview: HR picks anyone and
        // sees their slip computed live from the current configuration, before
        // (and without) running the whole payroll.
        $employee = $employeeId !== null
            ? Employee::forTenant($tenantId)->where('status', 'active')->find($employeeId)
            : null;

        $employee ??= Employee::forTenant($tenantId)
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
                    'employee_id' => $employee->id,
                    'payslip_id' => $payslipId,
                    'earnings' => array_map(
                        fn (array $row): array => [
                            'k' => $row['name'],
                            'v' => $this->rupiah($row['amount']),
                            'why' => $this->explainEarning($row, $pay),
                        ],
                        $pay['earnings'],
                    ),
                    'deductions' => array_map(
                        fn (array $row): array => [
                            'k' => $row['name'],
                            'v' => $this->rupiah($row['amount']),
                            'why' => $this->explainDeduction($row, $pay, $employee, $tenantId),
                        ],
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
            'employee_id' => $employee?->id,
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
     * One plain sentence saying where an earning line's number comes from —
     * so "setting ini darimana?" is answered on the slip itself instead of in
     * a support chat.
     *
     * @param  array{name: string, amount: float}  $row
     * @param  array<string, mixed>  $pay
     */
    private function explainEarning(array $row, array $pay): ?string
    {
        if ($row['name'] === 'Lembur') {
            $overtime = $pay['overtime_snapshot'] ?? null;

            if (is_array($overtime) && ($overtime['hourly_rate'] ?? 0) > 0) {
                return sprintf(
                    'Upah sejam = basis Rp %s ÷ %d = Rp %s, × pengali jenis hari × %s jam lembur disetujui%s. Aturan: Payroll → Setup Lembur.',
                    number_format((float) $overtime['basis'], 0, ',', '.'),
                    (int) $overtime['divisor'],
                    number_format((float) $overtime['hourly_rate'], 0, ',', '.'),
                    rtrim(rtrim(number_format((float) $overtime['hours'], 2, ',', '.'), '0'), ','),
                    ($overtime['basis_floored'] ?? false) ? ' (basis dinaikkan ke 75% penghasilan bulanan sesuai PP 35/2021)' : '',
                );
            }

            return null;
        }

        $factor = (float) ($pay['proration_factor'] ?? 1);

        if ($factor < 1) {
            return sprintf(
                'Nominal dari Master Gaji, diprorata %s%% karena bergabung/berhenti di tengah periode.',
                rtrim(rtrim(number_format($factor * 100, 1, ',', '.'), '0'), ','),
            );
        }

        return 'Nominal dari Payroll → Master Gaji.';
    }

    /**
     * The matching explanation for a deduction line.
     *
     * @param  array{name: string, amount: float, loan_id?: int}  $row
     * @param  array<string, mixed>  $pay
     */
    private function explainDeduction(array $row, array $pay, Employee $employee, int $tenantId): ?string
    {
        if ($row['name'] === 'PPh 21') {
            $tax = $pay['tax_snapshot'] ?? [];
            $method = $tax['method'] ?? null;

            if ($method === 'ter_bulanan') {
                return sprintf(
                    'TER bulanan: bruto pajak Rp %s × tarif %s%% (kategori %s, status PTKP %s). Tabel: Payroll → Tarif TER PPh 21.',
                    number_format((float) ($tax['base'] ?? $tax['gross'] ?? 0), 0, ',', '.'),
                    rtrim(rtrim(number_format(((float) ($tax['ter_rate'] ?? 0)) * 100, 2, ',', '.'), '0'), ','),
                    $tax['ter_category'] ?? '—',
                    $tax['ptkp_status'] ?? 'TK/0 bawaan — profil pajak belum diisi',
                );
            }

            if ($method !== null) {
                return 'Rekonsiliasi tahunan tarif progresif Pasal 17 (masa pajak terakhir) terhadap PPh 21 yang sudah dipotong.';
            }

            return null;
        }

        if (str_starts_with($row['name'], 'BPJS')) {
            $bpjs = $pay['bpjs_snapshot'] ?? [];
            $programs = $bpjs['programs'] ?? [];

            if ($programs !== []) {
                $parts = collect($programs)
                    ->filter(fn (array $line): bool => ($line['employee'] ?? 0) > 0)
                    ->map(fn (array $line, string $code): string => strtoupper($code).' Rp '.number_format((float) $line['employee'], 0, ',', '.'))
                    ->values()
                    ->implode(' + ');

                return sprintf(
                    '%s — porsi karyawan dari upah dasar BPJS Rp %s. Persentase: Payroll → BPJS & Pajak.',
                    $parts,
                    number_format((float) ($bpjs['base_wage'] ?? 0), 0, ',', '.'),
                );
            }

            return null;
        }

        if ($row['name'] === 'Denda Absensi') {
            $window = $pay['payday_snapshot']['window'] ?? null;

            $fines = AttendancePenalty::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('penalty_type', 'deduction')
                ->where('status', 'active')
                ->when(is_array($window), fn ($query) => $query->whereBetween('date', $window))
                ->orderBy('date')
                ->limit(4)
                ->get(['date', 'amount', 'notes']);

            if ($fines->isNotEmpty()) {
                $lines = $fines->take(3)
                    ->map(fn (AttendancePenalty $fine): string => sprintf(
                        '%s (Rp %s, %s)',
                        $fine->notes ?? 'Pelanggaran absensi',
                        number_format((float) $fine->amount, 0, ',', '.'),
                        Carbon::parse($fine->date)->translatedFormat('j M'),
                    ))
                    ->implode('; ');

                return $lines
                    .($fines->count() > 3 ? '; dan lainnya' : '')
                    .'. Aturan: Kehadiran → Sanksi Absensi.';
            }

            return null;
        }

        if (isset($row['loan_id'])) {
            return 'Cicilan otomatis pinjaman/kasbon aktif — berhenti sendiri saat lunas.';
        }

        return 'Nominal dari Payroll → Master Gaji.';
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
        $effectiveMasterId = SalaryMasterAssignment::effectiveMasterId(
            $employee,
            $period->end_date ?? $period->pay_date,
        );
        $master = $effectiveMasterId !== null
            ? SalaryMaster::forTenant($tenantId)->find($effectiveMasterId)
            : null;

        // Mapping Payday, when the employee is in a group, states the cut-off the
        // attendance and overtime windows follow — it is the narrower, more
        // specific answer, so it wins over the Master Gaji window.
        $payday = $employee->payday_id !== null
            ? Payday::forTenant($tenantId)->find($employee->payday_id)
            : null;

        $presentDays = 0;
        $overtimeRecords = collect();
        $attendanceRange = null;

        if ($period->start_date !== null && $period->end_date !== null) {
            $paydayRange = $payday?->cut_off_start_day !== null && $payday?->cut_off_end_day !== null
                ? $this->masterDateRange($period, $payday->cut_off_start_day, $payday->cut_off_end_day, null)
                : null;

            $attendanceRange = $paydayRange
                ?? ($master?->attendance_start_day !== null && $master?->attendance_end_day !== null
                    ? $this->masterDateRange($period, $master->attendance_start_day, $master->attendance_end_day, $master->attendance_period)
                    : [$period->start_date->toDateString(), $period->end_date->toDateString()]);

            $overtimeRange = $paydayRange
                ?? ($master?->overtime_start_day !== null && $master?->overtime_end_day !== null
                    ? $this->masterDateRange($period, $master->overtime_start_day, $master->overtime_end_day, $master->overtime_period)
                    : $attendanceRange);

            // Late is still a worked day — the reports and the rekap have
            // always counted it as hadir, and the late fine is the intended
            // penalty. Counting only 'present' here silently took the day's
            // meal/transport money AND the fine from the same morning.
            $presentDays = Attendance::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->whereBetween('date', $attendanceRange)
                ->whereIn('status', ['present', 'late'])
                ->count();

            $overtimeRecords = OvertimeRequest::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', $overtimeRange)
                ->get(['date', 'day_type', 'hours']);
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
        $overtimeBasis = 0.0;
        $bpjsBasis = 0.0;
        $hasCustomOvertime = false;

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->inForce()
            ->effectiveOn($period->end_date)
            ->with('component')
            ->get();

        $salarySources = $salaryComponents->map(fn (EmployeeSalaryComponent $row): array => [
            'id' => $row->id,
            'component_id' => $row->payroll_component_id,
            'component' => $row->component?->code,
            'amount' => (float) $row->amount,
            'salary_master_id' => $row->salary_master_id,
            'effective_start_date' => $row->effective_start_date?->toDateString(),
            'effective_end_date' => $row->effective_end_date?->toDateString(),
            'change_set_id' => $row->salary_change_set_id,
        ])->values()->all();

        // Component ids already paid, so a Master Gaji checklist does not
        // double-count a component already sourced from salary/position rows.
        $handledComponentIds = [];

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component === null) {
                continue;
            }

            // A component switched off in Master Komponen stops being paid —
            // otherwise "Nonaktifkan" changes nothing on a payslip.
            if (! $this->componentIsActive($component)) {
                continue;
            }

            $handledComponentIds[$component->id] = true;

            if ($component->basis_type !== null) {
                [$amount, $proratable] = $this->derivedComponentAmount($component, $employee, (float) $salaryComponent->amount, $presentDays, $overtimeHours, $tenantId, $period->end_date);
                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic, $overtimeBasis, $bpjsBasis);
            } else {
                // A percentage attached to an employee is still a percentage —
                // paying the figure itself would put "10%" on the payslip as
                // Rp 10.
                $amount = $component->calc_basis === 'percentage'
                    ? $this->amountForBasis(
                        (float) $salaryComponent->amount,
                        'percentage',
                        $presentDays,
                        $overtimeHours,
                        $this->percentageBase($component, $employee, $tenantId, $period->end_date),
                    )
                    : (float) $salaryComponent->amount;

                $this->collectComponent($component, $amount, true, $earnings, $deductions, $basic, $overtimeBasis, $bpjsBasis);
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

                if (! $this->componentIsActive($component)) {
                    continue;
                }

                $handledComponentIds[$component->id] = true;

                if ($component->calc_basis === 'per_overtime_hour') {
                    $hasCustomOvertime = true;
                }

                if ($component->basis_type !== null) {
                    [$amount] = $this->derivedComponentAmount($component, $employee, (float) $masterComponent->amount, $presentDays, $overtimeHours, $tenantId, $period->end_date);
                } else {
                    // The template's own nominal is the primary source; fall back
                    // to the dimension-mapped Nilai Komponen when it is unset.
                    $base = (float) $masterComponent->amount;

                    if ($base <= 0.0) {
                        $base = $this->resolveComponentValue($component, $employee, $tenantId) ?? 0.0;
                    }

                    $amount = $this->amountForBasis(
                        $base,
                        $component->calc_basis,
                        $presentDays,
                        $overtimeHours,
                        $this->percentageBase($component, $employee, $tenantId, $period->end_date),
                    );
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

                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic, $overtimeBasis, $bpjsBasis);
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

        // Capture the full monthly figures before proration: the overtime rate is
        // built from a whole month's wage even when the month itself is partial.
        $fullBasic = $basic;
        $fullEarnings = (float) array_sum(array_column($earnings, 'amount'));

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
            // The contribution follows the wage actually paid, as the basic
            // wage already did before the base became configurable.
            $bpjsBasis = round($bpjsBasis * $factor);
        }

        // Statutory overtime pay (PP 35/2021): the basis is Gaji Pokok plus the
        // allowances checked into "Komponen Overtime", floored at 75% of monthly
        // earnings, divided by 173 for an hourly wage and multiplied per the
        // tenant's rate table. Skipped when the tenant already models overtime as
        // a position component.
        $overtimeSnapshot = null;

        if (! $hasCustomOvertime && $overtimeRecords->isNotEmpty()) {
            $policy = OvertimeRules::policyFor($tenantId);

            // No component is flagged as overtime basis yet: fall back to the
            // basic wage rather than paying nothing.
            $configuredBasis = $overtimeBasis > 0.0 ? $overtimeBasis : $fullBasic;

            $resolved = OvertimeRules::basisFor(
                $configuredBasis,
                $fullEarnings,
                (float) $policy->fixed_basis_min_ratio,
            );

            $divisor = max(1, (int) $policy->hours_divisor);
            $rates = OvertimeRules::ratesFor($tenantId);
            $overtimePay = $this->computeOvertimePay($resolved['basis'], $overtimeRecords, $rates, $divisor);

            if ($overtimePay > 0) {
                $earnings[] = ['name' => 'Lembur', 'amount' => $overtimePay, 'proratable' => false, 'taxable' => true];

                $overtimeSnapshot = [
                    'basis' => round($resolved['basis']),
                    'basis_floored' => $resolved['floored'],
                    'hourly_rate' => round($resolved['basis'] / $divisor),
                    'divisor' => $divisor,
                    'hours' => $overtimeHours,
                ];
            }
        }

        $gross = (float) array_sum(array_column($earnings, 'amount'));

        // Taxable base = only earnings flagged taxable (manual "Perhitungan Pajak").
        $taxableGross = (float) array_sum(array_map(
            static fn (array $row): float => ($row['taxable'] ?? true) ? (float) $row['amount'] : 0.0,
            $earnings,
        ));

        // Recurring loan installments deducted from take-home pay.
        [$recurring, $loanIds] = $this->recurringDeductions($employee, $tenantId);

        foreach ($recurring as $line) {
            $deductions[] = $line;
        }

        // Attendance fines the tenant's own penalty tiers produced, read over
        // the same window the attendance itself was counted in — a fine dated
        // outside this month's cut-off belongs to another payslip.
        $fine = $this->attendanceFineFor($employee, $tenantId, $attendanceRange);

        if ($fine['amount'] > 0) {
            $deductions[] = [
                'name' => 'Denda Absensi'.($fine['count'] > 1 ? " ({$fine['count']}x)" : ''),
                'amount' => $fine['amount'],
            ];
        }

        // Statutory deductions computed from internal config (no external API).
        // Ahead of the tax, not after it: the company's JKK/JKM/Kesehatan
        // premiums are the employee's income, so PPh 21 cannot be worked out
        // until they are known.
        // The contribution base: the components flagged "ikut basis BPJS", or
        // the basic wage when nothing is flagged. An employee's separately
        // reported wage still overrides both, inside computeBpjs().
        $bpjs = $this->computeBpjs(
            $employee,
            $tenantId,
            match (true) {
                $bpjsBasis > 0 => $bpjsBasis,
                $basic > 0 => $basic,
                default => $gross,
            },
            $period->end_date,
        );

        $taxableGross += (float) ($bpjs['taxable_company'] ?? 0.0);

        // December (or the employee's final tax month) reconciles the year against
        // the progressive Pasal 17 tariff — but only for subjects that withhold
        // monthly via TER (pegawai tetap / PNS). Every other subject (komisaris,
        // pegawai tidak tetap, bukan pegawai, peserta, mantan pegawai) is taxed
        // per masa pajak with no annual reconciliation.
        $pph21 = ($this->isFinalTaxMonth($employee, $period)
            && Pph21Calculator::needsAnnualReconciliation($this->taxSubjectOf($employee, $tenantId)))
            ? $this->computeAnnualPph21($employee, $tenantId, $period, $taxableGross, (float) ($bpjs['deductible_employee'] ?? 0.0))
            : $this->computePph21($employee, $tenantId, $taxableGross, $period);

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
            'overtime_snapshot' => $overtimeSnapshot,
            // Which cut-off and pay date the employee's Mapping Payday group
            // produced, so the configuration is legible from the payslip.
            'payday_snapshot' => $payday !== null ? [
                'name' => $payday->name,
                'pay_label' => $payday->payLabel(),
                'pay_date' => $period->end_date !== null
                    ? $payday->payDateFor($period->end_date->copy())->toDateString()
                    : null,
                'cut_off' => $payday->cutOffLabel(),
                'window' => $attendanceRange,
            ] : null,
            'salary_sources' => $salarySources,
            'salary_master_id' => $effectiveMasterId,
            'proration_factor' => $factor,
            'loan_ids' => $loanIds,
            'basic' => $basic,
            'bpjs_employee' => $bpjs['employee'],
            'bpjs_company' => $bpjs['company'],
            'bpjs_snapshot' => $bpjs['snapshot'],
            'pph21' => $pph21['amount'],
            'tax_snapshot' => $pph21['snapshot'],
            // Kept per month so December can add up the same measure it charged
            // on all year, rather than re-deriving it from the payslip gross.
            'taxable_gross' => $taxableGross,
            'tax_deductible_premium' => (float) ($bpjs['deductible_employee'] ?? 0.0),
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
     * Compute statutory overtime pay (PP 35/2021) across the approved records.
     *
     * Each record is paid on its own — the multiplier resets at the start of
     * every overtime stretch — and against the band its day type selects, so a
     * Sunday is not paid at workday rates.
     *
     * @param  Collection<int, OvertimeRequest>  $records
     * @param  array<string, list<array{from: int, to: int|null, multiplier: float}>>  $rates
     */
    private function computeOvertimePay(float $monthlyWage, Collection $records, array $rates, int $divisor): float
    {
        if ($monthlyWage <= 0) {
            return 0.0;
        }

        // Rounded to whole rupiah before the multipliers are applied: wages are
        // paid in rupiah, and the setup documentation works its example the
        // same way (12.350.000 ÷ 173 = 71.387, then × the multiplier).
        $hourlyRate = round($monthlyWage / max(1, $divisor));
        $total = 0.0;

        foreach ($records as $record) {
            $hours = (float) $record->hours;

            if ($hours <= 0) {
                continue;
            }

            $dayType = OvertimeRules::normaliseDayType($record->day_type);
            $total += $hourlyRate * OvertimeRules::multiplierFor($rates, $dayType, $hours);
        }

        return round($total);
    }

    /**
     * Build recurring loan deduction lines for the employee and the source loan
     * ids so the finalize step can advance their installments.
     *
     * Cash advances are deliberately absent: they are an operational float
     * accounted for with receipts through a settlement, never docked from pay.
     *
     * @return array{0: list<array{name: string, amount: float, loan_id: int}>, 1: list<int>}
     */
    private function recurringDeductions(Employee $employee, int $tenantId): array
    {
        $lines = [];
        $loanIds = [];

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

        return [$lines, $loanIds];
    }

    /**
     * Sum an employee's full monthly fixed wage (earnings only, no proration and
     * no attendance-variable components) — the basis for THR and severance.
     */
    private function monthlyBaseWage(Employee $employee, int $tenantId, CarbonInterface|string|null $on = null): float
    {
        $total = 0.0;
        $handledComponentIds = [];

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->inForce()
            ->effectiveOn($on)
            ->with('component')
            ->get();

        foreach ($salaryComponents as $salaryComponent) {
            $component = $salaryComponent->component;

            if ($component !== null && $component->type !== 'deduction') {
                $handledComponentIds[$component->id] = true;
                $total += (float) $salaryComponent->amount;
            }
        }

        $effectiveMasterId = SalaryMasterAssignment::effectiveMasterId($employee, $on);

        if ($effectiveMasterId !== null) {
            $masterComponents = SalaryMasterComponent::query()
                ->where('salary_master_id', $effectiveMasterId)
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
     *
     * For a "Persentase" component the amount is a percentage, not rupiah, so
     * it only means something against the component it is a percentage of —
     * `$percentBase`, which the caller resolves for the employee.
     */
    private function amountForBasis(float $amount, ?string $calcBasis, int $presentDays, float $overtimeHours = 0.0, float $percentBase = 0.0): float
    {
        return match ($calcBasis) {
            'per_present_day' => $amount * $presentDays,
            'per_overtime_hour' => $amount * $overtimeHours,
            'percentage' => round($amount / 100 * $percentBase),
            default => $amount,
        };
    }

    /**
     * What a "Persentase" component takes its percentage of: the component it
     * points at, or Gaji Pokok when it points at nothing — the default the
     * setup documentation assumes ("Tunjangan Kinerja — % dari Gaji Pokok").
     *
     * Resolved through the same operand lookup a formula uses, so a percentage
     * reads the employee's own figure, then their Master Gaji template, then
     * the component's fixed nominal.
     */
    private function percentageBase(PayrollComponent $component, Employee $employee, int $tenantId, CarbonInterface|string|null $on = null): float
    {
        if ($component->calc_basis !== 'percentage') {
            return 0.0;
        }

        $reference = $component->percentage_of_component_id !== null
            ? PayrollComponent::forTenant($tenantId)->find($component->percentage_of_component_id)
            : PayrollComponent::forTenant($tenantId)->where('code', 'BASIC')->first();

        return $this->componentOperandValue($reference, $employee, $tenantId, $on);
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
    private function derivedComponentAmount(PayrollComponent $component, Employee $employee, float $attachmentAmount, int $presentDays, float $overtimeHours, int $tenantId, CarbonInterface|string|null $on = null): array
    {
        $attendanceBasis = ['per_present_day', 'per_overtime_hour'];
        $percentBase = $this->percentageBase($component, $employee, $tenantId, $on);

        switch ($component->basis_type) {
            case 'fixed':
                $amount = $this->amountForBasis((float) $component->basis_value, $component->calc_basis, $presentDays, $overtimeHours, $percentBase);

                return [$amount, ! in_array($component->calc_basis, $attendanceBasis, true)];

            case 'tabel':
                $base = $this->resolveComponentValue($component, $employee, $tenantId) ?? $attachmentAmount;
                $amount = $this->amountForBasis($base, $component->calc_basis, $presentDays, $overtimeHours, $percentBase);

                return [$amount, ! in_array($component->calc_basis, $attendanceBasis, true)];

            case 'formula':
                return [$this->evaluateComponentFormula($component, $employee, $tenantId, $on), false];

            default:
                $amount = $this->amountForBasis($attachmentAmount, $component->calc_basis, $presentDays, $overtimeHours, $percentBase);

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
    private function evaluateComponentFormula(PayrollComponent $component, Employee $employee, int $tenantId, CarbonInterface|string|null $on = null): float
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
                ? $this->resolveUmr($employee, $tenantId, $on)
                : $this->componentOperandValue($item->component, $employee, $tenantId, $on);

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
    private function componentOperandValue(?PayrollComponent $component, Employee $employee, int $tenantId, CarbonInterface|string|null $on = null): float
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
            ->inForce()
            ->effectiveOn($on)
            ->where('payroll_component_id', $component->id)
            ->value('amount');

        if ($salaryAmount !== null) {
            return (float) $salaryAmount;
        }

        $effectiveMasterId = SalaryMasterAssignment::effectiveMasterId($employee, $on);

        if ($effectiveMasterId !== null) {
            $masterAmount = SalaryMasterComponent::query()
                ->where('salary_master_id', $effectiveMasterId)
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
    private function resolveUmr(Employee $employee, int $tenantId, CarbonInterface|string|null $on = null): float
    {
        $date = $on instanceof CarbonInterface ? $on : Carbon::parse($on ?? now());
        $rate = UmrRate::forTenant($tenantId)
            ->where('year', '<=', (int) $date->year)
            ->where(fn ($q) => $q->where('branch_id', $employee->branch_id)->orWhereNull('branch_id'))
            ->orderByRaw('branch_id IS NULL') // branch-specific rate wins over the default
            ->orderByDesc('year')
            ->value('amount');

        return $rate !== null ? (float) $rate : $this->monthlyBaseWage($employee, $tenantId, $date);
    }

    /**
     * Compute the BPJS employee/company contribution from active internal rates.
     *
     * @return array{employee: float, company: float, snapshot: array<string, mixed>}
     */
    private function computeBpjs(Employee $employee, int $tenantId, float $fallbackWage, ?CarbonInterface $on = null): array
    {
        $date = ($on ?? now())->toDateString();
        $profile = EmployeeBpjsProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->where(fn ($query) => $query->whereNull('effective_start_date')->orWhereDate('effective_start_date', '<=', $date))
            ->where(fn ($query) => $query->whereNull('effective_end_date')->orWhereDate('effective_end_date', '>=', $date))
            ->orderByDesc('effective_start_date')
            ->first();

        // BPJS only applies to enrolled employees (those with a profile).
        if ($profile === null) {
            return [
                'employee' => 0.0,
                'company' => 0.0,
                'taxable_company' => 0.0,
                'deductible_employee' => 0.0,
                'snapshot' => [],
            ];
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
        $taxableCompany = 0.0;
        $deductibleEmployee = 0.0;
        $lines = [];

        $programs = BpjsProgram::where('is_active', true)
            ->with(['rates' => fn ($query) => $query
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('effective_start_date')->orWhereDate('effective_start_date', '<=', $date))
                ->where(fn ($q) => $q->whereNull('effective_end_date')->orWhereDate('effective_end_date', '>=', $date))
                ->orderByDesc('effective_start_date')])
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

            if (in_array($code, self::TAXABLE_COMPANY_PREMIUMS, true)) {
                $taxableCompany += $companyPortion;
            }

            if (in_array($code, self::DEDUCTIBLE_EMPLOYEE_PREMIUMS, true)) {
                $deductibleEmployee += $employeePortion;
            }

            $lines[$code] = [
                'rate_id' => $rate->id,
                'effective_start_date' => $rate->effective_start_date?->toDateString(),
                'effective_end_date' => $rate->effective_end_date?->toDateString(),
                'employee_rate' => (float) $rate->employee_rate,
                'company_rate' => (float) $rate->company_rate,
                'max_wage' => (float) $rate->max_wage,
                'employee' => $employeePortion,
                'company' => $companyPortion,
            ];
        }

        return [
            'employee' => $employeeTotal,
            'company' => $companyTotal,
            // What the tax engine needs out of this: the slice of the company's
            // premium that counts as the employee's income, and the slice of
            // the employee's own that comes off gross at year end.
            'taxable_company' => $taxableCompany,
            'deductible_employee' => $deductibleEmployee,
            'snapshot' => [
                'base_wage' => $base,
                'programs' => $lines,
                'taxable_company' => $taxableCompany,
                'deductible_employee' => $deductibleEmployee,
            ],
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
    /**
     * PPh 21 on a THR payment.
     *
     * THR is penghasilan tidak teratur: under PMK 168 it joins the month's
     * regular bruto and the TER rate is looked up on the total, which usually
     * lands a bracket or two higher than the salary alone. The withholding on
     * the THR itself is therefore what the combined amount costs minus what the
     * salary alone would have — not a rate applied to the THR in isolation.
     *
     * Only the subjects that withhold monthly via TER get this treatment;
     * nobody else receives a THR under Permenaker 6/2016 anyway.
     *
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    private function computeThrPph21(Employee $employee, int $tenantId, float $thr, float $regularGross, ?PayrollPeriod $period = null): array
    {
        $profile = TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        $subject = $profile?->tax_subject ?? 'pegawai_tetap';

        if ((bool) $profile?->is_pph21_exempt) {
            return $this->exemptPph21($profile, $thr);
        }

        if ($thr <= 0 || ! in_array($subject, ['pegawai_tetap', 'pns', 'komisaris'], true)) {
            return ['amount' => 0.0, 'snapshot' => []];
        }

        // The TER table in force for the period the THR is paid in.
        $on = ($period?->end_date ?? now())->toDateString();

        $category = Pph21Ter::category($profile?->ptkp_status, $on);
        $combined = $regularGross + $thr;

        $taxOnCombined = $combined * Pph21Ter::monthlyRate($category, $combined, $on);
        $taxOnRegular = $regularGross * Pph21Ter::monthlyRate($category, $regularGross, $on);

        $amount = max(0.0, round($taxOnCombined - $taxOnRegular));

        return [
            'amount' => $amount,
            'snapshot' => [
                'method' => 'ter_bulanan_thr',
                'subject' => $subject,
                'ptkp_status' => $profile?->ptkp_status,
                'ter_category' => $category,
                'regular_gross' => round($regularGross),
                'thr' => round($thr),
                'combined_gross' => round($combined),
                'ter_rate_combined' => Pph21Ter::monthlyRate($category, $combined, $on),
                'ter_rate_regular' => Pph21Ter::monthlyRate($category, $regularGross, $on),
                'pph21_amount' => $amount,
            ],
        ];
    }

    private function computePph21(Employee $employee, int $tenantId, float $gross, ?PayrollPeriod $period = null): array
    {
        $profile = TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        if ((bool) $profile?->is_pph21_exempt) {
            return $this->exemptPph21($profile, $gross);
        }

        // The period's own date, so a re-run of an old month resolves the TER
        // table and the PKP brackets that were in force then.
        $on = $period?->end_date ?? now();
        $year = (int) $on->year;

        $result = Pph21Calculator::compute(
            $profile?->tax_subject ?? 'pegawai_tetap',
            $profile?->ptkp_status,
            $gross,
            [
                'wage_basis' => $profile?->wage_basis ?? 'monthly',
                'daily_wage' => $profile?->daily_wage !== null ? (float) $profile->daily_wage : null,
                'effective_on' => $on->toDateString(),
            ],
            fn (float $base): float => $this->progressiveTax($base, $tenantId, $year),
        );

        return [
            'amount' => $result['amount'],
            'snapshot' => [
                'method' => $result['method'],
                'subject' => $result['subject'],
                'ptkp_status' => $result['ptkp_status'],
                'ter_category' => $result['ter_category'],
                'ter_rate' => $result['ter_rate'],
                'base' => $result['base'],
                'gross' => $result['gross'],
                'pph21_amount' => $result['amount'],
            ],
        ];
    }

    /**
     * Active attendance fines for the employee inside the payroll window.
     *
     * Only penalties recorded as a deduction count; a warning is a note on the
     * record, not money. Manually issued fines land here too, which is what an
     * HR desk expects after typing one in.
     *
     * @param  array{0: string, 1: string}|null  $window
     * @return array{amount: float, count: int}
     */
    private function attendanceFineFor(Employee $employee, int $tenantId, ?array $window): array
    {
        if ($window === null) {
            return ['amount' => 0.0, 'count' => 0];
        }

        // The tenant's late-fine table applies to everyone by itself: the run
        // generates the missing penalty rows for this employee's window before
        // reading them, so HR never has to press "Buat dari Absensi" for a
        // period. Idempotent — rows already generated (or made by hand) stand.
        AttendanceFines::generate($tenantId, $window[0], $window[1], $employee->id);

        $fines = AttendancePenalty::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('penalty_type', 'deduction')
            ->where('status', 'active')
            ->whereBetween('date', $window)
            ->get(['amount']);

        return [
            'amount' => round((float) $fines->sum('amount'), 2),
            'count' => $fines->count(),
        ];
    }

    /**
     * The nil result for an employee the tenant does not withhold PPh 21 for.
     *
     * The snapshot still records why nothing was deducted, so a payslip that
     * shows no tax line can be explained months later without guessing.
     *
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    private function exemptPph21(?TaxProfile $profile, float $gross): array
    {
        return [
            'amount' => 0.0,
            'snapshot' => [
                'method' => 'exempt',
                'subject' => $profile?->tax_subject ?? 'pegawai_tetap',
                'ptkp_status' => $profile?->ptkp_status,
                'gross' => round($gross),
                'exempt_reason' => $profile?->pph21_exempt_reason,
                'pph21_amount' => 0.0,
            ],
        ];
    }

    /**
     * Whether the employee is marked as not subject to PPh 21 withholding.
     */
    private function isPph21Exempt(Employee $employee, int $tenantId): bool
    {
        return (bool) TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->value('is_pph21_exempt');
    }

    /**
     * The PPh 21 subject category configured for an employee (defaults to
     * pegawai tetap when no tax profile exists).
     */
    private function taxSubjectOf(Employee $employee, int $tenantId): string
    {
        return TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->value('tax_subject') ?? 'pegawai_tetap';
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
    private function computeAnnualPph21(
        Employee $employee,
        int $tenantId,
        PayrollPeriod $period,
        float $currentGross,
        float $currentDeductiblePremium = 0.0,
    ): array {
        $profile = TaxProfile::where('tenant_id', $tenantId)
            ->where('employee_id', $employee->id)
            ->first();

        if ((bool) $profile?->is_pph21_exempt) {
            return $this->exemptPph21($profile, $currentGross);
        }

        $year = (int) ($period->start_date?->year ?? now()->year);

        // Year-to-date figures from every earlier run item this calendar year.
        $prior = PayrollRunItem::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('payroll_period_id', '!=', $period->id)
            ->whereHas('period', fn ($query) => $query->whereYear('start_date', $year))
            ->get(['taxable_gross', 'tax_deductible_premium', 'pph21_total']);

        $ytdGross = (float) $prior->sum('taxable_gross');
        $ytdWithheld = (float) $prior->sum('pph21_total');
        $ytdPremium = (float) $prior->sum('tax_deductible_premium');

        $annualGross = $ytdGross + $currentGross;
        $biayaJabatan = min($annualGross * 0.05, 6_000_000);
        $ptkp = $this->ptkpFor($profile?->ptkp_status, $tenantId, $year);
        // The employee's own JHT and JP for the year come off gross here — the
        // monthly TER never allowed for them, because TER is charged on bruto.
        $pensionPremium = $ytdPremium + $currentDeductiblePremium;

        $pkp = max(0.0, $annualGross - $biayaJabatan - $pensionPremium - $ptkp);
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
                'pension_premium' => round($pensionPremium),
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
     * @param  float  $overtimeBasis  running total of the earnings marked "Tetap"
     * @param  float  $bpjsBasis  running total of the earnings flagged "ikut basis BPJS"
     */
    private function collectComponent(
        PayrollComponent $component,
        float $amount,
        bool $proratable,
        array &$earnings,
        array &$deductions,
        float &$basic,
        float &$overtimeBasis,
        float &$bpjsBasis,
    ): void {
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

        // "Tetap" on the Master Komponen, surfaced as the basis checklist on
        // Setup Lembur: PP 35/2021 Pasal 30 builds the overtime rate from Gaji
        // Pokok plus the fixed allowances, not the basic wage alone.
        if ($component->is_fixed) {
            $overtimeBasis += $amount;
        }

        // "Ikut basis BPJS" on the Master Komponen: which earnings the
        // contribution is computed from, when the employee has no separately
        // reported wage.
        if ($component->is_bpjs_base) {
            $bpjsBasis += $amount;
        }
    }

    /**
     * Whether a component is switched on in Master Komponen.
     *
     * The column has carried both `active` and null over its life, and a row
     * that never had a status set was always paid — so only an explicit
     * non-active value takes a component out of the run.
     */
    private function componentIsActive(PayrollComponent $component): bool
    {
        $status = $component->status;

        return $status === null || $status === '' || $status === 'active';
    }

    /**
     * Format a numeric value as an Indonesian rupiah string.
     */
    private function rupiah(int|float|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }
}
