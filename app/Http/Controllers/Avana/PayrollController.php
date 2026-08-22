<?php

namespace App\Http\Controllers\Avana;

use App\Exceptions\Pph21ConfigurationException;
use App\Exports\PayrollTransferExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\PayrollPeriodResource;
use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AuditLog;
use App\Models\BpjsProgram;
use App\Models\Company;
use App\Models\DayCalcMethod;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\IncentiveCalculation;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\Payday;
use App\Models\PayrollComponent;
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
use App\Models\Tenant;
use App\Models\UmrRate;
use App\Services\OvertimePayableHours;
use App\Services\SalaryMasterAssignment;
use App\Support\AttendanceFines;
use App\Support\BasicWageComponent;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
     * Payslip label for each programme's employee premium.
     *
     * The premium is deducted per programme rather than as one "BPJS
     * (Karyawan)" figure: HR reconciles a payslip against their own sheet line
     * by line, and a single total hides which rate or which contribution base
     * produced it.
     *
     * @var array<string, string>
     */
    private const BPJS_EMPLOYEE_LABELS = [
        'kesehatan' => 'BPJS Kesehatan (Karyawan)',
        'jht' => 'JHT (Karyawan)',
        'jp' => 'JP (Karyawan)',
        'jkk' => 'JKK (Karyawan)',
        'jkm' => 'JKM (Karyawan)',
    ];

    public function __construct(private readonly OvertimePayableHours $overtimeHoursVerifier) {}

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
        return $this->staleReason($tenantId, $run) !== null;
    }

    /**
     * What changed since the run was computed, or null when nothing did.
     *
     * Used for the warning on screen AND as the gate on approve/lock: a run
     * computed on Monday, approved on Wednesday and locked on Friday pays what
     * Monday's data said, even if somebody raised a salary, corrected an
     * attendance, approved an incentive or filed a rapel in between. Finalising
     * stale figures is how an employee gets paid a number nobody can point at.
     */
    private function staleReason(int $tenantId, ?PayrollRun $run): ?string
    {
        if ($run === null || $run->status === PayrollRun::STATUS_LOCKED) {
            return null;
        }

        // An imported payroll is not derived from this data at all, so none of
        // it can make the upload out of date.
        if ($run->source === PayrollRun::SOURCE_IMPORT) {
            return null;
        }

        // The moment the run was calculated — not the items' updated_at, which a
        // recomputation producing identical figures never touches.
        $computedAt = $run->computed_at?->toDateTimeString()
            ?? PayrollRunItem::where('payroll_run_id', $run->id)->max('updated_at');

        if ($computedAt === null) {
            return null;
        }

        $period = $run->period ?? PayrollPeriod::find($run->payroll_period_id);

        foreach ($this->payrollInputChanges($tenantId, $period) as $label => $changedAt) {
            if ($changedAt !== null && $changedAt > $computedAt) {
                return $label;
            }
        }

        return null;
    }

    /**
     * Every input the run was computed from, with the moment it last changed:
     * the tenant's configuration, and the transactions dated inside the period.
     *
     * @return array<string, string|null>
     */
    private function payrollInputChanges(int $tenantId, ?PayrollPeriod $period): array
    {
        $changes = ['konfigurasi payroll (komponen, Master Gaji, gaji karyawan, lembur, denda, payday, BPJS)' => $this->latestConfigChangeAt($tenantId, $period)];

        $start = $period?->start_date?->toDateString();
        $end = $period?->end_date?->toDateString();

        if ($start === null || $end === null) {
            return $changes;
        }

        $between = fn (string $table, string $column) => DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereBetween($column, [$start, $end])
            ->max('updated_at');

        return $changes + [
            'data kehadiran di periode ini' => $between('attendances', 'date'),
            'pengajuan lembur di periode ini' => $between('overtime_requests', 'date'),
            'koreksi gaji di periode ini' => $between('payroll_corrections', 'correction_date'),
            'rapel di periode ini' => $between('salary_rapels', 'posting_date'),
            'insentif periode ini' => DB::table('incentive_calculations')
                ->where('tenant_id', $tenantId)
                ->where('payroll_period_id', $period->id)
                ->max('updated_at'),
            'pinjaman/cicilan karyawan' => DB::table('loans')
                ->where('tenant_id', $tenantId)
                ->max('updated_at'),
        ];
    }

    /**
     * The most recent moment any payroll input this tenant edits was changed:
     * components and their values, salary templates and per-employee rows,
     * overtime policy/multipliers, late-fine tiers, payday groups and BPJS
     * enrolments.
     *
     * Salary rows are dated, so they are judged against the period rather than
     * counted wholesale: a raise that starts in October cannot change what an
     * August run pays, and warning about it told HR to recompute a period the
     * change never touches.
     */
    private function latestConfigChangeAt(int $tenantId, ?PayrollPeriod $period = null): ?string
    {
        $masterIds = DB::table('salary_masters')->where('tenant_id', $tenantId)->pluck('id');
        $start = $period?->start_date?->toDateString();
        $end = $period?->end_date?->toDateString();

        $stamps = [
            DB::table('payroll_components')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('payroll_component_values')->where('tenant_id', $tenantId)->max('updated_at'),
            DB::table('salary_masters')->where('tenant_id', $tenantId)->max('updated_at'),
            $masterIds->isEmpty() ? null : DB::table('salary_master_components')->whereIn('salary_master_id', $masterIds)->max('updated_at'),
            DB::table('employee_salary_components')
                ->where('tenant_id', $tenantId)
                ->when($start !== null && $end !== null, fn ($query) => $query
                    ->where(fn ($from) => $from
                        ->whereNull('effective_start_date')
                        ->orWhereDate('effective_start_date', '<=', $end))
                    ->where(fn ($to) => $to
                        ->whereNull('effective_end_date')
                        ->orWhereDate('effective_end_date', '>=', $start)))
                ->max('updated_at'),
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

            // Only the revision still in play is recomputed. After an unlock the
            // finalised run is closed off, so this opens the next revision
            // instead of writing over what the payslips already said.
            $run = PayrollRun::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->whereNull('branch_id')
                ->current()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first() ?? new PayrollRun([
                    'tenant_id' => $tenantId,
                    'payroll_period_id' => $period->id,
                    'branch_id' => null,
                    'revision' => 1 + (int) PayrollRun::forTenant($tenantId)
                        ->where('payroll_period_id', $period->id)
                        ->max('revision'),
                ]);
            // Recomputing over an uploaded payroll would silently replace the
            // tenant's own figures with the engine's. Both are legitimate, but
            // swapping one for the other is a decision, not a side effect.
            if ($run->exists && $run->source === PayrollRun::SOURCE_IMPORT && $run->items()->exists()) {
                throw ValidationException::withMessages([
                    'payroll' => 'Periode ini memakai payroll hasil impor. Menghitung ulang akan menimpanya — kosongkan hasil impor lebih dulu bila memang ingin dihitung sistem.',
                ]);
            }

            $run->fill([
                'status' => 'calculated',
                'computed_at' => now(),
                'source' => PayrollRun::SOURCE_ENGINE,
                'reconciliation' => null,
                'run_by' => $request->user()->id,
                'approved_by' => null,
                'approved_at' => null,
                'approval_note' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ])->save();

            $employees = $this->payableEmployees($tenantId, $period);

            // A PTKP status nobody mapped to a TER category cannot be taxed on
            // a guess: the whole run stops until the configuration is fixed,
            // rather than withholding these employees at Kategori A.
            $this->assertPtkpMapped($employees, $tenantId, $period);

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
                            'overtime_records' => $pay['overtime_records'],
                            'incentives' => $pay['incentives'],
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

        return back()->with('success', 'Payroll dihitung');
    }

    /**
     * Stop the run when any employee's PTKP status is missing from the tax
     * profile, or is not covered by the TER category mapping in force.
     *
     * Both cases used to resolve to Kategori A — the wrong tax, charged
     * quietly, for as many months as it took somebody to notice. A payroll that
     * refuses to run names exactly whose configuration to fix.
     *
     * @param  Collection<int, Employee>  $employees
     *
     * @throws ValidationException
     */
    private function assertPtkpMapped(iterable $employees, int $tenantId, PayrollPeriod $period): void
    {
        $on = ($period->end_date ?? now())->toDateString();

        $statuses = TaxProfile::where('tenant_id', $tenantId)
            ->pluck('ptkp_status', 'employee_id');

        $unmapped = [];

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

            if (! Pph21Ter::hasCategory($statuses[$employee->id] ?? null, $on)) {
                $unmapped[] = (string) $employee->full_name;
            }
        }

        if ($unmapped === []) {
            return;
        }

        throw ValidationException::withMessages([
            'payroll' => count($unmapped).' karyawan punya status PTKP yang kosong atau tidak ada di mapping Kategori TER, '
                .'jadi payroll dihentikan: '
                .implode(', ', array_slice($unmapped, 0, 5))
                .(count($unmapped) > 5 ? ', …' : '')
                .'. Lengkapi di Konfigurasi Payroll → Profil Pajak, atau tambahkan statusnya di Tarif TER PPh 21.',
        ]);
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

        // Two periods covering the same day would each pick up the attendance,
        // overtime, corrections and rapel dated in the overlap — the same work
        // paid twice, in two different payslips.
        $overlapping = PayrollPeriod::forTenant($tenantId)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->first();

        if ($overlapping !== null) {
            throw ValidationException::withMessages([
                'start_date' => 'Rentang ini beririsan dengan periode '
                    .($overlapping->name ?? $overlapping->code)
                    .' ('.$overlapping->start_date->format('d M Y').'–'.$overlapping->end_date->format('d M Y').'). '
                    .'Satu hari kerja hanya boleh masuk satu periode payroll.',
            ]);
        }

        $salaryChangeInsidePeriod = EmployeeSalaryComponent::forTenant($tenantId)
            ->where(fn ($query) => $query->whereNull('status')->orWhereIn('status', ['active', 'pending_approval']))
            ->whereDate('effective_start_date', '>', $data['start_date'])
            ->whereDate('effective_start_date', '<=', $data['end_date'])
            ->exists();

        if ($salaryChangeInsidePeriod) {
            throw ValidationException::withMessages([
                'start_date' => 'Rentang periode membelah perubahan gaji yang sudah dijadwalkan. Sesuaikan awal/akhir periode agar perubahan gaji jatuh tepat pada awal periode.',
            ]);
        }

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

            $this->assertPtkpMapped($employees, $tenantId, $period);

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
    public function transferFile(Request $request): StreamedResponse|BinaryFileResponse|RedirectResponse
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

        // The generic layout is the sheet finance reads and forwards, so it goes
        // out as a proper document. The per-bank layouts stay bare CSV: an
        // upload form rejects a file carrying a letterhead.
        if ($format === 'generic') {
            return $this->transferWorkbook($request, $run, $periodCode, $note);
        }

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
     * The disbursement list as a finished document: the company's letterhead,
     * the period being paid, one row per employee and a total, with the amounts
     * written as rupiah.
     */
    private function transferWorkbook(Request $request, PayrollRun $run, string $periodCode, string $note): BinaryFileResponse
    {
        $company = Company::forTenant($request->user()->tenant_id)->first();
        $tenant = $request->user()->tenant;

        $logo = $company?->logo_path;
        $logoFile = $logo !== null && Storage::disk('public')->exists($logo)
            ? Storage::disk('public')->path($logo)
            : null;

        $rows = [];

        foreach ($run->items as $item) {
            $employee = $item->employee;
            $bank = $employee?->primaryBankAccount();

            $rows[] = [
                'name' => $employee?->full_name ?? '-',
                'bank' => $bank?->bank_name ?? '-',
                'account' => $bank?->account_number ?? '-',
                'holder' => $bank?->account_holder ?? $employee?->full_name ?? '-',
                'net' => (int) round((float) $item->net_salary),
            ];
        }

        $export = new PayrollTransferExport(
            $rows,
            $company?->legal_name ?: ($company?->name ?: ($tenant?->company_name ?: ($tenant?->name ?? 'Perusahaan'))),
            $run->period?->name ?? $periodCode,
            $note,
            $logoFile,
        );

        return Excel::download($export, 'daftar-transfer-'.$periodCode.'-'.now()->format('Y-m-d').'.xlsx');
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

        $item->loadMissing('run:id,status');

        $html = view('pdf.payslip', [
            // Admins may preview a payslip before the run is locked; the sheet
            // is stamped so a preview cannot be handed over as the real thing.
            'final' => $item->isPublished(),
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
                [...($snapshot['earnings'] ?? []), ...$this->refundLines($snapshot['deductions'] ?? [])],
            ),
            'deductions' => array_map(
                fn (array $row): array => ['name' => $row['name'], 'amount' => $this->rupiah($row['amount'])],
                $this->chargedDeductions($snapshot['deductions'] ?? []),
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
                ->current()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            abort_if($run === null, 404);

            if (! in_array($run->status, ['approved', 'locked'], true)) {
                throw ValidationException::withMessages([
                    'payroll' => 'Payroll harus direview & disetujui sebelum dikunci.',
                ]);
            }

            // Checked again here, not only at approval: the gap between signing
            // off and locking is exactly where a late salary change or an
            // attendance correction slips in.
            $stale = $this->staleReason($tenantId, $run);

            if ($stale !== null) {
                throw ValidationException::withMessages([
                    'payroll' => 'Ada perubahan pada '.$stale.' setelah payroll dihitung. Jalankan ulang lalu setujui lagi sebelum mengunci.',
                ]);
            }

            // Finalizing advances loan/cash-advance installments exactly once.
            if ($run->status !== 'locked') {
                $this->advanceInstallments($run, $tenantId);
                $run->update(['status' => 'locked']);
            }

            // The incentives this period paid are now history: locked rows are
            // read-only, and a recalculation skips them.
            IncentiveCalculation::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->where('status', IncentiveCalculation::STATUS_APPROVED)
                ->update(['status' => IncentiveCalculation::STATUS_LOCKED]);

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
                ->current()
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            abort_if($run === null, 404);

            // Reverse the installment advances made when the period was locked so
            // reopening does not leave loans/advances over-counted.
            if ($run->status === 'locked') {
                $this->reverseInstallments($run, $tenantId);

                // The finalised run stays exactly as it was paid; it is closed
                // off, and the next calculation writes a new revision.
                $run->update(['superseded_at' => now()]);
            }

            // Incentives paid by the reopened period go back to approved: they
            // are still signed off, but the period may now be recomputed.
            IncentiveCalculation::forTenant($tenantId)
                ->where('payroll_period_id', $period->id)
                ->where('status', IncentiveCalculation::STATUS_LOCKED)
                ->update(['status' => IncentiveCalculation::STATUS_APPROVED]);

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
                ->current()
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

            $stale = $this->staleReason($tenantId, $run);

            if ($stale !== null) {
                throw ValidationException::withMessages([
                    'payroll' => 'Ada perubahan pada '.$stale.' setelah payroll dihitung. Jalankan ulang perhitungan sebelum menyetujui.',
                ]);
            }

            // An imported payroll never passed the engine's checks, so signing
            // it off is a statement about somebody else's figures: it needs a
            // note saying the reconciliation was read.
            if ($run->source === PayrollRun::SOURCE_IMPORT && blank($data['note'] ?? null)) {
                throw ValidationException::withMessages([
                    'note' => 'Payroll hasil impor: tulis catatan persetujuan setelah memeriksa rekonsiliasinya (selisih, karyawan terlewat, karyawan tak terduga).',
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
                ->current()
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
            try {
                $pay = $this->computeEmployeePay($employee, $period, $tenantId);
            } catch (Pph21ConfigurationException $exception) {
                // The sample slip is a convenience, not the payroll itself: a
                // tax profile nobody has filled in should send HR to the screen
                // that fixes it, not take the whole Payroll page down with a
                // 500. Running payroll still refuses, by name, in
                // assertPtkpMapped().
                return [
                    'employee' => $employee->full_name,
                    'employee_id' => $employee->id,
                    'notice' => 'Slip contoh belum bisa dihitung: '.$exception->getMessage()
                        .' Lengkapi Status PTKP karyawan ini di BPJS & Pajak → Profil Pajak.',
                    'earnings' => [],
                    'deductions' => [],
                    'gross' => $this->rupiah(0),
                    'deduction' => $this->rupiah(0),
                    'net' => $this->rupiah(0),
                ];
            }

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
                        [...$pay['earnings'], ...$this->refundLines($pay['deductions'])],
                    ),
                    'deductions' => array_map(
                        fn (array $row): array => [
                            'k' => $row['name'],
                            'v' => $this->rupiah($row['amount']),
                            'why' => $this->explainDeduction($row, $pay, $employee, $tenantId),
                        ],
                        $this->chargedDeductions($pay['deductions']),
                    ),
                    'tax_info' => $this->slipTaxInfo($pay),
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
        if (str_starts_with($row['name'], 'Pengembalian PPh 21')) {
            return $this->explainTaxRefund($row, $pay);
        }

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
     * Why an annual over-withholding is being paid back on this slip.
     *
     * @param  array{name: string, amount: float}  $row
     * @param  array<string, mixed>  $pay
     */
    private function explainTaxRefund(array $row, array $pay): string
    {
        $tax = $pay['tax_snapshot'] ?? [];

        return sprintf(
            'Rekonsiliasi tahunan: PPh 21 setahun Rp %s lebih kecil dari yang sudah dipotong Rp %s, '
                .'jadi selisih Rp %s dikembalikan pada slip ini.',
            number_format((float) ($tax['annual_tax'] ?? 0), 0, ',', '.'),
            number_format((float) ($tax['ytd_withheld'] ?? 0), 0, ',', '.'),
            number_format((float) ($tax['tax_refund'] ?? abs($row['amount'])), 0, ',', '.'),
        );
    }

    /**
     * The tax basis rows on the payslip: what the TER rate was applied to, and
     * which rate came out of the table.
     *
     * They are shown, not deducted — the withholding itself is the PPh 21 line
     * among the deductions. Their job is to make the number checkable against a
     * manual sheet without opening the run snapshot.
     *
     * @param  array<string, mixed>  $pay
     * @return list<array{k: string, v: string, why: ?string}>
     */
    private function slipTaxInfo(array $pay): array
    {
        /** @var array<string, mixed> $tax */
        $tax = $pay['tax_snapshot'] ?? [];

        if ($tax === []) {
            return [];
        }

        $taxableGross = (float) ($pay['taxable_gross'] ?? 0);
        $employerPremium = (float) ($pay['tax_employer_premium'] ?? 0);

        $rows = [[
            'k' => 'Bruto Pajak',
            'v' => $this->rupiah((float) ($tax['base'] ?? $tax['gross'] ?? $taxableGross)),
            'why' => sprintf(
                'Pendapatan yang ditandai Perhitungan Pajak = Ya Rp %s%s. '
                    .'Komponen yang tidak kena pajak diatur di Payroll → Master Komponen.',
                number_format(max(0.0, $taxableGross - $employerPremium), 0, ',', '.'),
                match (true) {
                    $employerPremium > 0 => sprintf(
                        ' + premi BPJS (JKK, JKM, Kesehatan) yang dibayar perusahaan Rp %s — premi itu penghasilan karyawan menurut PMK 168/2023',
                        number_format($employerPremium, 0, ',', '.'),
                    ),
                    (float) ($pay['bpjs_snapshot']['taxable_company'] ?? 0) > 0 => sprintf(
                        ' — premi BPJS perusahaan Rp %s tidak ikut dihitung, sesuai pengaturan di Payroll → BPJS & Pajak',
                        number_format((float) $pay['bpjs_snapshot']['taxable_company'], 0, ',', '.'),
                    ),
                    default => '',
                },
            ),
        ]];

        if (($tax['ter_rate'] ?? null) !== null) {
            $rows[] = [
                'k' => 'Tarif TER',
                'v' => sprintf(
                    '%s%% · Kategori %s',
                    rtrim(rtrim(number_format(((float) $tax['ter_rate']) * 100, 2, ',', '.'), '0'), ','),
                    $tax['ter_category'] ?? '—',
                ),
                'why' => sprintf(
                    'Status PTKP %s → Kategori %s (BPJS & Pajak → Profil Pajak), lalu bruto pajak dicocokkan ke bracket TER '
                        .'yang berlaku pada periode ini. Tabel: Payroll → Tarif TER PPh 21.',
                    $tax['ptkp_status'] ?? 'belum diisi',
                    $tax['ter_category'] ?? '—',
                ),
            ];
        }

        return $rows;
    }

    /**
     * The refund rows hiding among the deductions, as positive earnings.
     *
     * @param  array<int, array{name: string, amount: float}>  $deductions
     * @return array<int, array{name: string, amount: float}>
     */
    private function refundLines(array $deductions): array
    {
        return array_values(array_map(
            static fn (array $row): array => [...$row, 'amount' => abs((float) $row['amount'])],
            array_filter($deductions, static fn (array $row): bool => (float) $row['amount'] < 0),
        ));
    }

    /**
     * The deductions that really are deductions.
     *
     * @param  array<int, array{name: string, amount: float}>  $deductions
     * @return array<int, array{name: string, amount: float}>
     */
    private function chargedDeductions(array $deductions): array
    {
        return array_values(array_filter(
            $deductions,
            static fn (array $row): bool => (float) $row['amount'] >= 0,
        ));
    }

    /**
     * The matching explanation for a deduction line.
     *
     * @param  array{name: string, amount: float, loan_id?: int, bpjs_code?: string}  $row
     * @param  array<string, mixed>  $pay
     */
    private function explainDeduction(array $row, array $pay, Employee $employee, int $tenantId): ?string
    {
        if (str_starts_with($row['name'], 'Pengembalian PPh 21')) {
            return $this->explainTaxRefund($row, $pay);
        }

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

        if (isset($row['bpjs_code']) && $row['bpjs_code'] !== 'all') {
            $bpjs = $pay['bpjs_snapshot'] ?? [];
            $program = $bpjs['programs'][$row['bpjs_code']] ?? null;

            if ($program === null) {
                return null;
            }

            $base = (float) ($bpjs['base_wage'] ?? 0);
            $capped = (float) ($program['max_wage'] ?? 0) > 0
                ? min($base, (float) $program['max_wage'])
                : $base;

            return sprintf(
                '%s%% × upah dasar BPJS Rp %s%s. Persentase & batas upah: Payroll → BPJS & Pajak.',
                rtrim(rtrim(number_format(((float) ($program['employee_rate'] ?? 0)) * 100, 2, ',', '.'), '0'), ','),
                number_format($capped, 0, ',', '.'),
                $capped < $base
                    ? sprintf(' (dibatasi dari Rp %s oleh batas upah program)', number_format($base, 0, ',', '.'))
                    : '',
            );
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
                ->get();

            // An approval is a plan; attendance is the record. Pay the smaller
            // of the two, and keep the working on each request so the payslip
            // line can be explained. A re-run re-reads attendance, so an
            // attendance correction lands before the period is locked.
            $overtimeRecords = $this->overtimeHoursVerifier->verify($tenantId, $employee->id, $overtimeRecords);
        }

        $overtimeHours = $this->overtimeHoursVerifier->payableTotal($overtimeRecords);
        $overtimeAudit = $this->overtimeHoursVerifier->audit($overtimeRecords);

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

            if ($suppressFlatOvertime && $component->calc_basis === 'per_overtime_hour') {
                continue;
            }

            $handledComponentIds[$component->id] = true;

            if ($component->calc_basis === 'per_overtime_hour') {
                $hasCustomOvertime = true;
            }

            if ($component->basis_type !== null) {
                [$amount, $proratable] = $this->derivedComponentAmount($component, $employee, (float) $salaryComponent->amount, $presentDays, $overtimeHours, $tenantId, $period->end_date);
                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic, $overtimeBasis, $bpjsBasis);
            } else {
                $amount = $this->amountForBasis(
                    (float) $salaryComponent->amount,
                    $component->calc_basis,
                    $presentDays,
                    $overtimeHours,
                    $this->percentageBase($component, $employee, $tenantId, $period->end_date),
                );
                $proratable = ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true);

                $this->collectComponent($component, $amount, $proratable, $earnings, $deductions, $basic, $overtimeBasis, $bpjsBasis);
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
                    // The template's nominal is the only source. There is no
                    // fallback: a component checked into a template with no
                    // figure is worth nothing, because the alternative — reading
                    // some other table — is payroll paying a number nobody
                    // assigned on this screen.
                    $base = (float) $masterComponent->amount;

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

        // Insentif: only rows an approver signed off for this period, one line
        // per scheme. The unique key on (scheme, employee, period) is what keeps
        // a re-run from paying the same incentive twice — the rows are read,
        // never created, here. Its payslip name follows the scheme's component
        // so the taxable/BPJS treatment is the component's, not a guess.
        $incentives = IncentiveCalculation::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('payroll_period_id', $period->id)
            ->payable()
            ->with('scheme.component:id,name,is_taxable,is_bpjs_base')
            ->get();

        foreach ($incentives as $incentive) {
            $amount = (float) $incentive->amount;

            if ($amount <= 0.0) {
                continue;
            }

            $component = $incentive->scheme?->component;
            $label = $component?->name ?? ('Insentif: '.($incentive->scheme?->name ?? 'Lainnya'));

            $earnings[] = [
                'name' => $label,
                'amount' => $amount,
                // An incentive is earned for the period as a whole; a mid-period
                // joiner is prorated by the scheme itself, not again here.
                'proratable' => false,
                'taxable' => $component === null || (bool) $component->is_taxable,
            ];

            if ($component !== null && (bool) $component->is_bpjs_base) {
                $bpjsBasis += $amount;
            }
        }

        $incentiveSnapshot = $incentives
            ->map(fn (IncentiveCalculation $row): array => [
                'incentive_calculation_id' => $row->id,
                'scheme' => $row->scheme?->code,
                'amount' => (float) $row->amount,
                'status' => $row->status,
                'measured_value' => (float) $row->measured_value,
            ])
            ->values()
            ->all();

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
                    'requested_hours' => (float) $overtimeRecords->sum('hours'),
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

        // PMK 168/2023 counts the company's JKK/JKM/Kesehatan premium as the
        // employee's income, so it joins the TER base. A tenant whose payroll
        // desk withholds on the salary alone switches it off in BPJS & Pajak;
        // the premium is then reported but not taxed monthly.
        $employerPremiumIsTaxable = $this->employerPremiumIsTaxable($tenantId);
        $employerPremium = $employerPremiumIsTaxable
            ? (float) ($bpjs['taxable_company'] ?? 0.0)
            : 0.0;

        $taxableGross += $employerPremium;

        // December (or the employee's final tax month) reconciles the year against
        // the progressive Pasal 17 tariff — but only for subjects that withhold
        // monthly via TER (pegawai tetap / PNS). Every other subject (komisaris,
        // pegawai tidak tetap, bukan pegawai, peserta, mantan pegawai) is taxed
        // per masa pajak with no annual reconciliation.
        $pph21 = ($this->isFinalTaxMonth($employee, $period)
            && Pph21Calculator::needsAnnualReconciliation($this->taxSubjectOf($employee, $tenantId)))
            ? $this->computeAnnualPph21($employee, $tenantId, $period, $taxableGross, (float) ($bpjs['deductible_employee'] ?? 0.0))
            : $this->computePph21($employee, $tenantId, $taxableGross, $period);

        foreach ($this->bpjsEmployeeLines($bpjs) as $bpjsLine) {
            $deductions[] = $bpjsLine;
        }

        if ($pph21['amount'] > 0) {
            $deductions[] = ['name' => 'PPh 21', 'amount' => $pph21['amount']];
        } elseif ($pph21['amount'] < 0) {
            // A negative balance is over-withholding handed back: a deduction
            // line with a negative amount, so the payslip shows the refund
            // where the tax normally sits and the net rises by it.
            $deductions[] = [
                'name' => 'Pengembalian PPh 21 (lebih potong)',
                'amount' => $pph21['amount'],
            ];
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
            // Requested vs actual vs payable for every approved request in the
            // window — the audit trail behind the overtime line.
            'overtime_records' => $overtimeAudit,
            // Which approved incentives were paid into this payslip.
            'incentives' => $incentiveSnapshot,
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
            // The slice of the company's premium that entered the TER base —
            // zero when the tenant taxes the salary alone.
            'tax_employer_premium' => $employerPremium,
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
            // Payable, not requested: attendance has already capped this.
            $hours = (float) ($record->payable_hours ?? 0);

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

            if ($component !== null
                && $component->type !== 'deduction'
                && ! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true)) {
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
            : BasicWageComponent::for($tenantId);

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
     *   - fixed   : the employee's own nominal — their salary row, or the
     *               Master Gaji template it was copied from — x attendance
     *               calc_basis. Master Komponen holds no rupiah figure: two
     *               places to type a nominal means payroll can pay a number
     *               nobody assigned, which is exactly what it used to do when
     *               this read `basis_value`. A Persentase component is the one
     *               exception: its number is a percent, not rupiah, so it stays
     *               on the component.
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
                $base = $component->calc_basis === 'percentage'
                    ? (float) $component->basis_value
                    : $attachmentAmount;

                $amount = $this->amountForBasis($base, $component->calc_basis, $presentDays, $overtimeHours, $percentBase);

                return [$amount, ! in_array($component->calc_basis, $attendanceBasis, true)];

            case 'tabel':
                // Kept as a basis type for old data only: its figure now comes
                // from the same place as everything else, the employee's salary
                // or the Master Gaji it was copied from.
                $base = $attachmentAmount;
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

        // No fallback to the component's own figure: a rupiah nominal lives in
        // Master Gaji or on the employee's salary, nowhere else.
        return 0.0;
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
     * Whether the company-paid BPJS premium counts as the employee's income
     * for the month's TER base (PMK 168/2023), or is reported but not taxed
     * monthly because the tenant withholds on the salary alone.
     */
    private function employerPremiumIsTaxable(int $tenantId): bool
    {
        return (bool) (Tenant::find($tenantId)?->tax_includes_employer_bpjs ?? true);
    }

    /**
     * The employee's BPJS premium split into one deduction line per programme.
     *
     * Falls back to a single combined line whenever the per-programme figures
     * are missing or do not add up to the computed total — a payslip must never
     * deduct a different amount than the run recorded.
     *
     * @param  array{employee: float, snapshot: array<string, mixed>}  $bpjs
     * @return list<array{name: string, amount: float, bpjs_code: string}>
     */
    private function bpjsEmployeeLines(array $bpjs): array
    {
        $total = (float) ($bpjs['employee'] ?? 0.0);

        if ($total <= 0) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $programs */
        $programs = $bpjs['snapshot']['programs'] ?? [];
        $lines = [];

        foreach ($programs as $code => $program) {
            $amount = (float) ($program['employee'] ?? 0.0);

            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'name' => self::BPJS_EMPLOYEE_LABELS[$code] ?? strtoupper((string) $code).' (Karyawan)',
                'amount' => $amount,
                'bpjs_code' => (string) $code,
            ];
        }

        if ($lines === [] || round(array_sum(array_column($lines, 'amount'))) !== round($total)) {
            return [['name' => 'BPJS (Karyawan)', 'amount' => $total, 'bpjs_code' => 'all']];
        }

        return $lines;
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

        $category = Pph21Ter::categoryOrFail($profile?->ptkp_status, $on);
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

        $subject = $profile?->tax_subject ?? 'pegawai_tetap';
        $wageBasis = $profile?->wage_basis ?? 'monthly';

        // TER Bulanan is a rate on the calendar month's bruto, so a weekly or
        // biweekly run cannot be taxed on its own slice: each run within the
        // month is charged on the month to date and credited with what the
        // month's earlier finalised runs already withheld. Monthly cycles, a
        // daily wage and the Pasal 17 subjects are taxed per masa pajak as
        // before.
        $aggregatesMonth = $period !== null
            && ($period->cycle ?? 'monthly') !== 'monthly'
            && Pph21Calculator::usesMonthlyTer($subject, $wageBasis);

        $monthToDate = $aggregatesMonth
            ? $this->withheldHistory($tenantId, $employee, $period, true)
            : null;

        $priorGross = $monthToDate !== null ? (float) $monthToDate->sum('taxable_gross') : 0.0;
        $priorWithheld = $monthToDate !== null ? (float) $monthToDate->sum('pph21_total') : 0.0;

        $result = Pph21Calculator::compute(
            $subject,
            $profile?->ptkp_status,
            $gross + $priorGross,
            [
                'wage_basis' => $wageBasis,
                'daily_wage' => $profile?->daily_wage !== null ? (float) $profile->daily_wage : null,
                'effective_on' => $on->toDateString(),
            ],
            fn (float $base): float => $this->progressiveTax($base, $tenantId, $year),
        );

        $amount = round($result['amount'] - $priorWithheld);

        return [
            'amount' => $amount,
            'snapshot' => [
                'method' => $result['method'],
                'subject' => $result['subject'],
                'ptkp_status' => $result['ptkp_status'],
                'ter_category' => $result['ter_category'],
                'ter_rate' => $result['ter_rate'],
                'base' => $result['base'],
                'gross' => $result['gross'],
                'pph21_amount' => $amount,
                ...($aggregatesMonth ? [
                    'cycle' => $period?->cycle,
                    'month_gross' => round($gross + $priorGross),
                    'month_withheld_before' => round($priorWithheld),
                    'period_gross' => round($gross),
                ] : []),
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

        if ($employee->resign_date !== null
            && $period->start_date !== null
            && $employee->resign_date->between($period->start_date, $period->end_date)) {
            return true;
        }

        if ((int) $period->end_date->month !== 12) {
            return false;
        }

        // A weekly or biweekly cycle has four or five periods ending in
        // December, and only the last of them is the masa pajak terakhir —
        // reconciling on each of them would charge the annual tax over and
        // over. The one that covers 31 December is the final one; failing that
        // (a cut-off ending mid-December), the one with no later period behind
        // it in the same year.
        if ((int) $period->end_date->day === 31) {
            return true;
        }

        return ! PayrollPeriod::forTenant($period->tenant_id)
            ->where('id', '!=', $period->id)
            ->whereYear('end_date', (int) $period->end_date->year)
            ->whereDate('end_date', '>', $period->end_date->toDateString())
            ->exists();
    }

    /**
     * Earlier payslips of this employee that count as tax already withheld.
     *
     * Only approved and locked runs count: a draft or a recalculated run holds
     * figures that still change, and treating them as withheld would credit the
     * employee for tax nobody has paid. A period that was re-run keeps more
     * than one item, so only the newest run's item per period is taken —
     * otherwise a rerun doubles the year-to-date.
     *
     * @return Collection<int, PayrollRunItem>
     */
    private function withheldHistory(int $tenantId, Employee $employee, PayrollPeriod $period, bool $sameMonthOnly): Collection
    {
        $anchor = $period->end_date ?? $period->start_date ?? now();
        $year = (int) $anchor->year;
        $month = (int) $anchor->month;

        return PayrollRunItem::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where('payroll_period_id', '!=', $period->id)
            ->whereHas('run', fn ($query) => $query->whereIn('status', [
                PayrollRun::STATUS_APPROVED,
                PayrollRun::STATUS_LOCKED,
            ]))
            ->whereHas('period', function ($query) use ($year, $month, $sameMonthOnly): void {
                $query->whereYear('end_date', $year);

                if ($sameMonthOnly) {
                    $query->whereMonth('end_date', $month);
                }
            })
            ->orderBy('payroll_period_id')
            ->orderByDesc('payroll_run_id')
            ->get(['payroll_period_id', 'payroll_run_id', 'taxable_gross', 'tax_deductible_premium', 'pph21_total'])
            ->unique('payroll_period_id')
            ->values();
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

        // Year-to-date figures from every earlier *finalised* payslip this
        // calendar year — see withheldHistory() for why a draft run and a
        // re-run must not count.
        $prior = $this->withheldHistory($tenantId, $employee, $period, false);

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

        // The balance, signed. Withholding more than the year owes is normal —
        // TER charges on bruto and knows nothing of biaya jabatan, PTKP or the
        // employee's own JHT/JP — and the excess is the employee's money: it is
        // paid back on this payslip (PMK 168/2023 Pasal 21), not written off to
        // zero.
        $amount = round($annualTax - $ytdWithheld);
        $refund = $amount < 0 ? abs($amount) : 0.0;

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
                // Positive when the year over-withheld: what goes back to the
                // employee on this payslip.
                'tax_refund' => $refund,
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

        if (BasicWageComponent::matches($component->code)) {
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
