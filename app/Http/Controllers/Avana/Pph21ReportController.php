<?php

namespace App\Http\Controllers\Avana;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\TaxPeriodCompliance;
use App\Models\TaxProfile;
use App\Models\User;
use App\Support\FeatureGate;
use App\Support\Pph21Ter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The PPh 21 reporting screen: what a tax period owes, what was withheld,
 * whether the employee data behind it is complete, and how far the period has
 * travelled through deposit and filing.
 *
 * Everything numeric is read back from `payroll_run_items` — this screen never
 * recomputes tax, so it can never disagree with the payslips. The two facts
 * payroll cannot know (deposited, reported) live in `tax_period_compliances`
 * and are typed in here.
 */
class Pph21ReportController extends Controller
{
    /**
     * Run statuses whose withholding counts as actually deducted from an
     * employee. A calculated run is still being worked on and re-running it
     * replaces every figure, so its tax is owed but not yet withheld.
     *
     * @var array<int, string>
     */
    private const WITHHELD_STATUSES = [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_LOCKED];

    /**
     * How many past tax periods the monthly recap lists.
     */
    private const RECAP_LIMIT = 12;

    /**
     * Render the dashboard for one tax period (`?period`, default the latest).
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $selected = $this->resolvePeriod($request, $tenantId);

        $recapPeriods = PayrollPeriod::forTenant($tenantId)
            ->orderByDesc('start_date')
            ->limit(self::RECAP_LIMIT)
            ->get(['id', 'name', 'code', 'start_date', 'end_date', 'status']);

        // The selected period may be older than the recap window; include it so
        // its figures are always available to the aggregate queries below.
        $periodIds = $recapPeriods->pluck('id')->push($selected?->id)->filter()->unique()->values()->all();

        $totals = $this->periodTotals($tenantId, $periodIds);
        $readiness = $this->buktiPotongReadiness($tenantId, $periodIds);
        $compliances = TaxPeriodCompliance::forTenant($tenantId)
            ->whereIn('payroll_period_id', $periodIds)
            ->get()
            ->keyBy('payroll_period_id');

        $selectedTotals = $totals[$selected?->id] ?? $this->emptyTotals();
        $selectedReadiness = $readiness[$selected?->id] ?? 0;
        $compliance = $selected === null ? null : $compliances->get($selected->id);

        $previous = $this->previousPeriod($recapPeriods, $selected);
        $previousTotals = $totals[$previous?->id] ?? null;

        $search = trim((string) $request->input('search', ''));
        $scheme = trim((string) $request->input('scheme', ''));
        $perPage = min(max((int) $request->input('per_page', 20), 10), 100);

        [$employees, $employeeMeta] = $this->employeeRows($tenantId, $selected, $search, $scheme, $perPage);

        return Inertia::render('avana/pph21-report/index', [
            'periods' => $recapPeriods->map(fn (PayrollPeriod $period): array => [
                'id' => $period->id,
                'name' => (string) $period->name,
            ])->all(),
            'summary' => [
                'period' => $selected?->name,
                'period_id' => $selected?->id,
                'start_date' => $selected?->start_date?->toDateString(),
                'end_date' => $selected?->end_date?->toDateString(),
                'employee_count' => $selectedTotals['employees'],
                'employee_delta' => $previousTotals === null
                    ? null
                    : $selectedTotals['employees'] - $previousTotals['employees'],
                'gross' => $this->rupiah($selectedTotals['gross']),
                'gross_raw' => $selectedTotals['gross'],
                'gross_delta_pct' => $this->deltaPercent($selectedTotals['gross'], $previousTotals['gross'] ?? null),
                'previous_period' => $previous?->name,
                'tax_due' => $this->rupiah($selectedTotals['tax']),
                'tax_due_raw' => $selectedTotals['tax'],
                'tax_withheld' => $this->rupiah($selectedTotals['withheld']),
                'tax_withheld_raw' => $selectedTotals['withheld'],
                'withheld_pct' => $selectedTotals['tax'] > 0
                    ? round($selectedTotals['withheld'] / $selectedTotals['tax'] * 100, 1)
                    : 0.0,
            ],
            'compliance' => $this->complianceProps($selected, $selectedTotals, $selectedReadiness, $compliance),
            'completeness' => $this->completeness($tenantId, $selected, $selectedTotals['employees'], $selectedReadiness),
            'recap' => $recapPeriods->map(function (PayrollPeriod $period) use ($totals, $readiness, $compliances): array {
                $row = $totals[$period->id] ?? $this->emptyTotals();
                $record = $compliances->get($period->id);

                return [
                    'id' => $period->id,
                    'name' => (string) $period->name,
                    'employees' => $row['employees'],
                    'gross' => $this->rupiah($row['gross']),
                    'tax' => $this->rupiah($row['tax']),
                    'bukti_potong' => ($readiness[$period->id] ?? 0).' / '.$row['employees'],
                    'deposit_status' => $record?->deposit_status ?? TaxPeriodCompliance::STATUS_PENDING,
                    'report_status' => $record?->report_status ?? TaxPeriodCompliance::STATUS_PENDING,
                ];
            })->all(),
            'employees' => $employees,
            'employee_meta' => $employeeMeta,
            'filters' => [
                'period' => $selected?->id,
                'search' => $search,
                'scheme' => $scheme,
                'per_page' => $perPage,
            ],
            'can' => [
                'update_compliance' => $this->canUpdateCompliance($request),
            ],
        ]);
    }

    /**
     * Record that the period's PPh 21 was deposited to the state and/or
     * reported to DJP, with the receipt numbers that prove it.
     */
    public function updateCompliance(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $validated = $request->validate([
            'payroll_period_id' => [
                'required', 'integer',
                Rule::exists('payroll_periods', 'id')->where('tenant_id', $tenantId),
            ],
            'deposit_status' => ['required', Rule::in(TaxPeriodCompliance::STATUSES)],
            'deposit_date' => ['nullable', 'date'],
            'deposit_ntpn' => ['nullable', 'string', 'max:64'],
            'report_status' => ['required', Rule::in(TaxPeriodCompliance::STATUSES)],
            'report_date' => ['nullable', 'date'],
            'report_ntte' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // A period cannot be reported before it is deposited: the filing refers
        // to the deposit receipt. Catching it here keeps the recap honest.
        if ($validated['report_status'] === TaxPeriodCompliance::STATUS_DONE
            && $validated['deposit_status'] !== TaxPeriodCompliance::STATUS_DONE) {
            return back()->withErrors([
                'report_status' => 'Pelaporan tidak bisa ditandai selesai sebelum penyetoran selesai.',
            ]);
        }

        TaxPeriodCompliance::updateOrCreate(
            ['tenant_id' => $tenantId, 'payroll_period_id' => $validated['payroll_period_id']],
            [
                'deposit_status' => $validated['deposit_status'],
                'deposit_date' => $validated['deposit_date'] ?? null,
                'deposit_ntpn' => $validated['deposit_ntpn'] ?? null,
                'report_status' => $validated['report_status'],
                'report_date' => $validated['report_date'] ?? null,
                'report_ntte' => $validated['report_ntte'] ?? null,
                'note' => $validated['note'] ?? null,
                'updated_by' => $request->user()->id,
            ],
        );

        return back()->with('success', 'Status kepatuhan masa pajak diperbarui.');
    }

    /**
     * Download the selected period's per-employee PPh 21 detail as
     * csv (default), xlsx or pdf.
     */
    public function export(Request $request): HttpResponse
    {
        $this->ensureCan($request, 'export');

        $tenantId = (int) $request->user()->tenant_id;
        $selected = $this->resolvePeriod($request, $tenantId);

        abort_if($selected === null, 404);

        $header = [
            'Nama', 'NIP', 'NPWP', 'Status PTKP', 'Kategori TER', 'Tarif TER (%)',
            'Bruto Pajak', 'PPh 21', 'Metode', 'Status Run',
        ];

        $query = $this->itemQuery($tenantId, $selected)
            ->with(['employee:id,full_name,employee_number', 'run:id,status'])
            ->orderByDesc('pph21_total');

        $profiles = $this->profilesFor($tenantId);

        $mapper = function (PayrollRunItem $item) use ($profiles): array {
            $tax = is_array($item->calculation_snapshot) ? ($item->calculation_snapshot['tax'] ?? []) : [];
            $profile = $profiles[$item->employee_id] ?? null;

            return [
                $item->employee?->full_name,
                $item->employee?->employee_number,
                $profile->npwp ?? null,
                $tax['ptkp_status'] ?? $profile->ptkp_status ?? null,
                $tax['ter_category'] ?? null,
                $this->terRatePercent($tax),
                (int) $this->taxableGross($item),
                (int) $item->pph21_total,
                $this->methodLabel($tax['method'] ?? null),
                $item->run?->status,
            ];
        };

        $format = in_array($request->query('format'), ['xlsx', 'pdf'], true)
            ? $request->query('format')
            : 'csv';

        $base = 'pph21-'.str($selected->name)->slug().'-'.Carbon::today()->format('Y-m-d');
        $title = 'Laporan PPh 21 — '.$selected->name;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($header, $query, $mapper): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, $header);

                $query->chunk(500, function ($rows) use ($out, $mapper): void {
                    foreach ($rows as $row) {
                        fputcsv($out, $mapper($row));
                    }
                });

                fclose($out);
            }, $base.'.csv', ['Content-Type' => 'text/csv']);
        }

        $rows = $query->get()->map(fn (PayrollRunItem $item): array => $mapper($item))->all();

        if ($format === 'xlsx') {
            return Excel::download(new ReportExport($rows, $header, $title), $base.'.xlsx');
        }

        return Pdf::loadView('pdf.laporan', [
            'title' => $title,
            'subtitle' => 'Masa pajak '.$selected->name,
            'headings' => $header,
            'rows' => $rows,
            'generatedAt' => Carbon::now()->format('d M Y H:i'),
        ])->setPaper('a4', 'landscape')->download($base.'.pdf');
    }

    /* ---------------------------------------------------------------
     | Data building
     |--------------------------------------------------------------- */

    /**
     * The period the screen is showing: `?period` when given and owned by the
     * tenant, otherwise the most recent one.
     */
    private function resolvePeriod(Request $request, int $tenantId): ?PayrollPeriod
    {
        $period = $request->filled('period')
            ? PayrollPeriod::forTenant($tenantId)->find($request->integer('period'))
            : null;

        return $period ?? PayrollPeriod::forTenant($tenantId)
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * Headline figures per period in one grouped query: headcount, the base the
     * tax was actually charged on, what is owed, and what has been withheld.
     *
     * @param  array<int, int>  $periodIds
     * @return array<int, array{employees:int, gross:float, tax:float, withheld:float}>
     */
    private function periodTotals(int $tenantId, array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        $withheld = implode("','", self::WITHHELD_STATUSES);

        return DB::table('payroll_run_items as items')
            ->join('payroll_runs as runs', 'runs.id', '=', 'items.payroll_run_id')
            ->where('items.tenant_id', $tenantId)
            ->whereNull('runs.superseded_at')
            ->whereIn('runs.payroll_period_id', $periodIds)
            ->groupBy('runs.payroll_period_id')
            ->select([
                'runs.payroll_period_id as period_id',
                DB::raw('COUNT(DISTINCT items.employee_id) as employees'),
                // taxable_gross is the base the TER was charged on; runs closed
                // before that column existed have it backfilled from the payslip
                // gross, but a zero there still means "never computed".
                DB::raw('SUM(CASE WHEN items.taxable_gross > 0 THEN items.taxable_gross ELSE items.gross_salary END) as gross'),
                DB::raw('SUM(items.pph21_total) as tax'),
                DB::raw("SUM(CASE WHEN runs.status IN ('{$withheld}') THEN items.pph21_total ELSE 0 END) as withheld"),
            ])
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                (int) $row->period_id => [
                    'employees' => (int) $row->employees,
                    'gross' => (float) $row->gross,
                    'tax' => (float) $row->tax,
                    'withheld' => (float) $row->withheld,
                ],
            ])
            ->all();
    }

    /**
     * How many of each period's employees could have a 1721-A1 issued for them
     * — those whose PTKP status resolves to a real TER category. Without one
     * the form has no PTKP line and the annual recap cannot be computed.
     *
     * @param  array<int, int>  $periodIds
     * @return array<int, int>
     */
    private function buktiPotongReadiness(int $tenantId, array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        $rows = DB::table('payroll_run_items as items')
            ->join('payroll_runs as runs', 'runs.id', '=', 'items.payroll_run_id')
            ->join('tax_profiles as profiles', function ($join) use ($tenantId): void {
                $join->on('profiles.employee_id', '=', 'items.employee_id')
                    ->where('profiles.tenant_id', '=', $tenantId)
                    ->whereNull('profiles.deleted_at');
            })
            ->where('items.tenant_id', $tenantId)
            ->whereNull('runs.superseded_at')
            ->whereIn('runs.payroll_period_id', $periodIds)
            ->groupBy('runs.payroll_period_id', 'profiles.ptkp_status')
            ->select([
                'runs.payroll_period_id as period_id',
                'profiles.ptkp_status',
                DB::raw('COUNT(DISTINCT items.employee_id) as total'),
            ])
            ->get();

        $ready = [];

        foreach ($rows as $row) {
            // Validity is decided by the TER master, not by "not null": a typo
            // like "K/O" is stored happily and silently falls back to Kategori A.
            if (! Pph21Ter::hasCategory($row->ptkp_status)) {
                continue;
            }

            $periodId = (int) $row->period_id;
            $ready[$periodId] = ($ready[$periodId] ?? 0) + (int) $row->total;
        }

        return $ready;
    }

    /**
     * The five-step compliance checklist for the selected period. The first two
     * steps are read from payroll itself; the last two from what was typed in.
     *
     * @param  array{employees:int, gross:float, tax:float, withheld:float}  $totals
     * @return array<string, mixed>
     */
    private function complianceProps(
        ?PayrollPeriod $period,
        array $totals,
        int $readiness,
        ?TaxPeriodCompliance $record,
    ): array {
        $computed = $totals['employees'] > 0;
        $reviewed = $totals['withheld'] > 0 || ($computed && $totals['tax'] <= 0.0);
        $slipsReady = $computed && $readiness >= $totals['employees'];
        $deposited = $record?->depositDone() ?? false;
        $reported = $record?->reportDone() ?? false;

        $steps = [
            [
                'key' => 'perhitungan',
                'label' => 'Perhitungan payroll & PPh 21',
                'state' => $computed ? 'done' : 'pending',
                'detail' => $computed
                    ? $totals['employees'].' karyawan terhitung'
                    : 'Payroll masa pajak ini belum dijalankan',
            ],
            [
                'key' => 'review',
                'label' => 'Review & persetujuan payroll',
                'state' => $reviewed ? 'done' : ($computed ? 'warn' : 'pending'),
                'detail' => $reviewed
                    ? 'Run sudah disetujui atau dikunci'
                    : 'Run masih berstatus dihitung — pajak belum dianggap dipotong',
            ],
            [
                'key' => 'bukti_potong',
                'label' => 'Bukti potong',
                'state' => $slipsReady ? 'done' : ($computed ? 'warn' : 'pending'),
                'detail' => $readiness.' / '.$totals['employees'].' siap terbit',
            ],
            [
                'key' => 'setor',
                'label' => 'Pembayaran / setoran',
                'state' => $deposited ? 'done' : 'pending',
                'detail' => $deposited
                    ? 'NTPN '.($record?->deposit_ntpn ?: '—').' · '.($record?->deposit_date?->translatedFormat('d M Y') ?? '—')
                    : 'Belum disetor',
            ],
            [
                'key' => 'lapor',
                'label' => 'Pelaporan SPT Masa',
                'state' => $reported ? 'done' : 'pending',
                'detail' => $reported
                    ? 'NTTE '.($record?->report_ntte ?: '—').' · '.($record?->report_date?->translatedFormat('d M Y') ?? '—')
                    : 'Belum dilaporkan',
            ],
        ];

        $done = count(array_filter($steps, fn (array $step): bool => $step['state'] === 'done'));

        return [
            'period_id' => $period?->id,
            'steps' => $steps,
            'done' => $done,
            'total' => count($steps),
            'overall' => $done === count($steps) ? 'done' : ($done === 0 ? 'pending' : 'warn'),
            'record' => [
                'deposit_status' => $record?->deposit_status ?? TaxPeriodCompliance::STATUS_PENDING,
                'deposit_date' => $record?->deposit_date?->toDateString(),
                'deposit_ntpn' => $record?->deposit_ntpn,
                'report_status' => $record?->report_status ?? TaxPeriodCompliance::STATUS_PENDING,
                'report_date' => $record?->report_date?->toDateString(),
                'report_ntte' => $record?->report_ntte,
                'note' => $record?->note,
            ],
        ];
    }

    /**
     * Tax-data completeness for the selected period, with the employees behind
     * every shortfall so the gap can be closed rather than merely counted.
     *
     * @return array<string, mixed>
     */
    private function completeness(int $tenantId, ?PayrollPeriod $period, int $headcount, int $readiness): array
    {
        if ($period === null || $headcount === 0) {
            return ['bars' => [], 'issues' => [], 'issue_total' => 0];
        }

        $rows = DB::table('payroll_run_items as items')
            ->join('payroll_runs as runs', 'runs.id', '=', 'items.payroll_run_id')
            ->join('employees', 'employees.id', '=', 'items.employee_id')
            ->leftJoin('tax_profiles as profiles', function ($join) use ($tenantId): void {
                $join->on('profiles.employee_id', '=', 'items.employee_id')
                    ->where('profiles.tenant_id', '=', $tenantId)
                    ->whereNull('profiles.deleted_at');
            })
            ->where('items.tenant_id', $tenantId)
            ->whereNull('runs.superseded_at')
            ->where('runs.payroll_period_id', $period->id)
            ->select([
                'items.employee_id',
                'employees.full_name',
                'employees.employee_number',
                // The tax profile is the tax record of record; the employee's
                // own NIK stands in when the profile never filled one.
                DB::raw("COALESCE(NULLIF(profiles.nik, ''), employees.nik) as nik"),
                'profiles.npwp',
                'profiles.ptkp_status',
            ])
            ->distinct()
            ->get();

        $withNik = 0;
        $withNpwp = 0;
        $withPtkp = 0;
        $issues = [];

        foreach ($rows as $row) {
            $hasNik = filled($row->nik);
            $hasNpwp = filled($row->npwp);
            $hasPtkp = Pph21Ter::hasCategory($row->ptkp_status);

            $withNik += $hasNik ? 1 : 0;
            $withNpwp += $hasNpwp ? 1 : 0;
            $withPtkp += $hasPtkp ? 1 : 0;

            $missing = [];

            if (! $hasNik) {
                $missing[] = 'NIK';
            }

            if (! $hasNpwp) {
                $missing[] = 'NPWP';
            }

            if (! $hasPtkp) {
                $missing[] = filled($row->ptkp_status) ? 'PTKP tidak dikenal' : 'Status PTKP';
            }

            if ($missing !== []) {
                $issues[] = [
                    'employee_id' => (int) $row->employee_id,
                    'name' => (string) $row->full_name,
                    'employee_number' => $row->employee_number,
                    'missing' => $missing,
                ];
            }
        }

        return [
            'bars' => [
                ['label' => 'NIK', 'done' => $withNik, 'total' => $headcount],
                ['label' => 'NPWP', 'done' => $withNpwp, 'total' => $headcount],
                ['label' => 'Status PTKP', 'done' => $withPtkp, 'total' => $headcount],
                ['label' => 'Bukti potong siap', 'done' => $readiness, 'total' => $headcount],
            ],
            // Worst offenders first; the full list is a download away.
            'issues' => array_slice($issues, 0, 50),
            'issue_total' => count($issues),
        ];
    }

    /**
     * The paginated per-employee table for the selected period.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int|null>|null}
     */
    private function employeeRows(
        int $tenantId,
        ?PayrollPeriod $period,
        string $search,
        string $scheme,
        int $perPage,
    ): array {
        if ($period === null) {
            return [[], null];
        }

        $page = $this->itemQuery($tenantId, $period)
            // public_id is the employee's route key: the bukti-potong link is
            // built from it, and a numeric id there resolves to nothing.
            ->with(['employee:id,public_id,full_name,employee_number', 'run:id,status'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas(
                'employee',
                fn (Builder $sub) => $sub->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%"),
            ))
            ->when($scheme !== '', fn (Builder $query) => $query->where('calculation_snapshot->tax->method', $scheme))
            ->orderByDesc('pph21_total')
            ->paginate($perPage)
            ->withQueryString();

        $profiles = $this->profilesFor($tenantId);

        $rows = collect($page->items())
            ->map(function (PayrollRunItem $item) use ($profiles): array {
                $tax = is_array($item->calculation_snapshot) ? ($item->calculation_snapshot['tax'] ?? []) : [];
                $profile = $profiles[$item->employee_id] ?? null;
                $ptkp = $tax['ptkp_status'] ?? $profile->ptkp_status ?? null;

                return [
                    'id' => $item->id,
                    'employee_id' => (int) $item->employee_id,
                    'employee_route_key' => $item->employee?->public_id,
                    'name' => $item->employee?->full_name ?? '—',
                    'employee_number' => $item->employee?->employee_number,
                    'npwp' => $profile->npwp ?? null,
                    'ptkp_status' => $ptkp,
                    'ptkp_valid' => Pph21Ter::hasCategory($ptkp),
                    'ter_category' => $tax['ter_category'] ?? null,
                    'ter_rate' => $this->terRatePercent($tax),
                    'gross' => $this->rupiah($this->taxableGross($item)),
                    'tax' => $this->rupiah($item->pph21_total),
                    'method' => $tax['method'] ?? null,
                    'method_label' => $this->methodLabel($tax['method'] ?? null),
                    'run_status' => $item->run?->status,
                ];
            })
            ->all();

        return [$rows, [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
            'total' => $page->total(),
        ]];
    }

    /**
     * Payroll items of a period's live runs — superseded re-runs excluded, so a
     * recomputed month is never counted twice.
     *
     * @return Builder<PayrollRunItem>
     */
    private function itemQuery(int $tenantId, PayrollPeriod $period): Builder
    {
        return PayrollRunItem::query()
            ->forTenant($tenantId)
            ->whereHas('run', fn (Builder $run) => $run
                ->where('payroll_period_id', $period->id)
                ->whereNull('superseded_at'));
    }

    /**
     * Tax profiles keyed by employee — NPWP and the fallback PTKP status for
     * rows whose snapshot predates the field.
     *
     * @return array<int, TaxProfile>
     */
    private function profilesFor(int $tenantId): array
    {
        return TaxProfile::forTenant($tenantId)
            ->get(['id', 'employee_id', 'npwp', 'nik', 'ptkp_status'])
            ->keyBy('employee_id')
            ->all();
    }

    /**
     * The base the tax was charged on, falling back to the payslip gross for
     * runs closed before `taxable_gross` existed.
     */
    private function taxableGross(PayrollRunItem $item): float
    {
        $taxable = (float) $item->taxable_gross;

        return $taxable > 0 ? $taxable : (float) $item->gross_salary;
    }

    /**
     * The TER rate the row was charged at, as a percentage.
     *
     * A THR row records no plain `ter_rate`: its tax is the difference between
     * two TER runs, so the meaningful figure is the rate the combined income
     * landed on. Anything else (Pasal 17, an exemption) has no TER rate at all.
     *
     * @param  array<string, mixed>  $tax
     */
    private function terRatePercent(array $tax): ?float
    {
        $rate = $tax['ter_rate'] ?? $tax['ter_rate_combined'] ?? null;

        return $rate === null ? null : round((float) $rate * 100, 2);
    }

    /**
     * The period immediately before the selected one, for the month-on-month
     * deltas on the KPI tiles.
     *
     * @param  Collection<int, PayrollPeriod>  $periods
     */
    private function previousPeriod($periods, ?PayrollPeriod $selected): ?PayrollPeriod
    {
        if ($selected === null) {
            return null;
        }

        return $periods->first(
            fn (PayrollPeriod $period): bool => $period->start_date?->lt($selected->start_date) ?? false,
        );
    }

    /**
     * Month-on-month change as a percentage, or null when there is nothing to
     * compare against.
     */
    private function deltaPercent(float $current, ?float $previous): ?float
    {
        if ($previous === null || $previous <= 0.0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * @return array{employees:int, gross:float, tax:float, withheld:float}
     */
    private function emptyTotals(): array
    {
        return ['employees' => 0, 'gross' => 0.0, 'tax' => 0.0, 'withheld' => 0.0];
    }

    /**
     * Human label for a withholding scheme recorded in the snapshot.
     */
    private function methodLabel(?string $method): ?string
    {
        return match ($method) {
            'ter_bulanan' => 'TER Bulanan',
            'ter_bulanan_thr' => 'TER Bulanan (THR)',
            'ter_harian' => 'TER Harian',
            'pasal17' => 'Pasal 17',
            '50pct_pasal17' => '50% × Pasal 17',
            'annual_reconciliation' => 'Rekonsiliasi Tahunan',
            'exempt' => 'Dikecualikan',
            default => $method,
        };
    }

    private function rupiah(int|float|string $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    /* ---------------------------------------------------------------
     | Authorisation
     |--------------------------------------------------------------- */

    /**
     * Authorize a `pph21.{action}` permission. The PPh 21 feature switch is
     * checked first: a tenant without it has no tax figures to report on.
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        FeatureGate::ensure($user, 'pph21');

        abort_unless($user->hasPermissionTo('pph21.'.$action), 403);
    }

    /**
     * Whether the viewer may type in the deposit/filing receipts, so the UI can
     * render the panel read-only instead of failing the request.
     */
    private function canUpdateCompliance(Request $request): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasPermissionTo('pph21.update');
    }
}
