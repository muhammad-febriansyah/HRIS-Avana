<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\AttendanceResource;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AbsenceMarker;
use App\Services\HrAttendanceCorrection;
use App\Support\PrivateFile;
use App\Support\TenantTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    /**
     * Sortable columns whitelist for the index DataTable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['clock_in_at', 'created_at'];

    /**
     * Indonesian labels for the attendance status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Alpa',
        'leave' => 'Cuti',
        'incomplete' => 'Belum Lengkap',
        'need_correction' => 'Perlu Koreksi',
    ];

    /**
     * Status filter value for the virtual "Belum Absen" rows. Not a stored
     * status — the row does not exist until the day closes as Alpa.
     */
    private const NOT_YET_IN_STATUS = 'not_in';

    /** Most rows one bulk correction may touch. */
    private const BULK_CORRECTION_LIMIT = 500;

    /**
     * Deterministic avatar background palette.
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display the daily attendance rekap with status KPIs for a single date.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        // Date range: `date_from`..`date_to`; a lone `date` sets both ends
        // (back-compat with the single-day URLs). Normalised so from <= to.
        $single = $request->query('date');
        $from = $this->resolveDate($request->query('date_from') ?? $single, $tenantId);
        $to = $this->resolveDate($request->query('date_to') ?? $single, $tenantId);
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $fromString = $from->format('Y-m-d');
        $toString = $to->format('Y-m-d');
        $isRange = $fromString !== $toString;

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $filters = $this->rekapFilters($request->query());
        $query = $this->rekapQuery($request->user(), (int) $tenantId, $fromString, $toString, $filters)
            ->with([
                'employee:id,full_name,employee_number,branch_id',
                'employee.branch:id,name',
                'shift:id,name,start_time,end_time',
                'workLocation:id,name,latitude,longitude,radius_meter',
            ]);

        // The day whose no-shows are listed: a single-day view of today or a
        // past day, or — for a range — today when the range covers it.
        $today = TenantTime::today($tenantId);
        $focusDate = ! $isRange
            ? ($fromString <= $today ? $fromString : null)
            : ($fromString <= $today && $today <= $toString ? $today : null);
        $notYetIn = $focusDate !== null ? $this->notYetIn($request->user(), (int) $tenantId, $focusDate, $filters['branch_id']) : null;
        $notYetInLabel = $focusDate === $today ? 'Belum Absen' : 'Belum Tercatat';

        // A single-day rekap lists the no-shows as rows of their own, so the
        // table HR reads shows everyone the day expects — not only those who
        // opened the app — and each one can be corrected from there. Ranges
        // keep the plain paginated query: merging a month in memory would
        // not scale.
        $attendances = $notYetIn !== null && ! $isRange && in_array($request->query('status'), [null, '', self::NOT_YET_IN_STATUS], true)
            ? $this->withNotYetInRows($request, $query->orderBy($sort, $direction)->get(), $notYetIn, $fromString, $notYetInLabel)
            : AttendanceResource::collection(
                $query->orderBy($sort, $direction)->paginate($request->integer('per_page', 10))->withQueryString(),
            );

        $statusCountQuery = Attendance::forTenant($tenantId)
            ->whereDate('date', '>=', $fromString)
            ->whereDate('date', '<=', $toString);

        $this->applyBranchScope($statusCountQuery, $request->user());

        $statusCounts = $statusCountQuery
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('avana/absensi/index', [
            'attendances' => $attendances,
            'filters' => array_merge(
                $request->only(['search', 'status', 'branch_id', 'sort', 'direction', 'per_page']),
                ['date_from' => $fromString, 'date_to' => $toString],
            ),
            'range' => [
                'from' => $fromString,
                'to' => $toString,
                'display' => $isRange
                    ? $from->format('d M').' – '.$to->format('d M Y')
                    : $from->format('d M Y'),
                'is_range' => $isRange,
            ],
            'kpis' => [
                'hadir' => (int) ($statusCounts['present'] ?? 0),
                'terlambat' => (int) ($statusCounts['late'] ?? 0),
                'izin' => (int) ($statusCounts['leave'] ?? 0),
                'alpa' => (int) ($statusCounts['absent'] ?? 0),
                'belum_absen' => $notYetIn === null ? null : count($notYetIn),
                'belum_absen_label' => $focusDate === $today ? 'Belum Absen (hari ini)' : 'Belum Tercatat',
            ],
            'branches' => Branch::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'can_correct' => $request->user()->can('approveCorrection', Attendance::class),
            'today' => TenantTime::today($tenantId),
        ]);
    }

    /**
     * HR corrects one employee's day straight from the rekap: set clock times
     * (status then follows the shift), or mark the day Cuti/Izin or Alpa.
     */
    public function correct(Request $request): RedirectResponse
    {
        $this->authorize('approveCorrection', Attendance::class);

        $user = $request->user();
        $tenantId = (int) $user->tenant_id;
        $today = TenantTime::today($tenantId);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'status' => ['required', Rule::in(HrAttendanceCorrection::STATUSES)],
            'clock_in' => ['nullable', 'required_if:status,'.HrAttendanceCorrection::STATUS_PRESENT, 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'date.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'clock_in.required_if' => 'Jam masuk wajib diisi untuk status Hadir.',
            'clock_in.date_format' => 'Format jam masuk harus HH:MM.',
            'clock_out.date_format' => 'Format jam pulang harus HH:MM.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
        ]);

        // The same branch fence as the rekap: HR of one branch cannot rewrite
        // another branch's attendance by posting an employee id.
        $employeeQuery = Employee::forTenant($tenantId)->whereKey($data['employee_id']);
        $this->applyBranchScope($employeeQuery, $user);
        $employee = $employeeQuery->firstOrFail();

        HrAttendanceCorrection::apply(
            $employee,
            $data['date'],
            $data['status'],
            $data['clock_in'] ?? null,
            $data['clock_out'] ?? null,
            $data['reason'],
            $user,
        );

        $response = back()->with('success', 'Absensi '.$employee->full_name.' tanggal '.Carbon::parse($data['date'])->translatedFormat('j M Y').' dikoreksi.');

        // A locked period's payslips are history: the run will not pick this
        // up again, so HR has to settle the difference as a pay correction.
        $lockedPeriod = PayrollPeriod::forTenant($tenantId)
            ->where('status', 'locked')
            ->whereDate('start_date', '<=', $data['date'])
            ->whereDate('end_date', '>=', $data['date'])
            ->first(['code']);

        if ($lockedPeriod !== null) {
            $response->with('warning', 'Periode payroll '.$lockedPeriod->code.' sudah dikunci, jadi gaji di periode itu tidak berubah otomatis. Selisihnya dicatat lewat Payroll → Koreksi Gaji.');
        }

        return $response;
    }

    /**
     * The rekap's own filters, read from a query string or a posted body.
     *
     * @param  array<string, mixed>  $source
     * @return array{search: string|null, status: string|null, branch_id: int|null}
     */
    private function rekapFilters(array $source): array
    {
        $search = is_string($source['search'] ?? null) && trim($source['search']) !== '' ? trim($source['search']) : null;
        $status = is_string($source['status'] ?? null) && $source['status'] !== '' ? $source['status'] : null;
        $branchId = is_numeric($source['branch_id'] ?? null) ? (int) $source['branch_id'] : null;

        return ['search' => $search, 'status' => $status, 'branch_id' => $branchId];
    }

    /**
     * Stored attendance rows the rekap shows for a range and its filters,
     * fenced to the viewer's branches.
     *
     * @param  array{search: string|null, status: string|null, branch_id: int|null}  $filters
     * @return Builder<Attendance>
     */
    private function rekapQuery(User $user, int $tenantId, string $from, string $to, array $filters): Builder
    {
        $query = Attendance::query()
            ->forTenant($tenantId)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($filters['search'], function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['branch_id'], fn ($q, $branchId) => $q->where('branch_id', $branchId));

        $this->applyBranchScope($query, $user);

        return $query;
    }

    /**
     * HR corrects many days at once: the rows ticked on the rekap, or every
     * row the current filters show ("pilih semua"). One status, one set of
     * clock times and one reason apply to all of them, each written and
     * logged exactly as a single correction would be. All or nothing.
     */
    public function correctMany(Request $request): RedirectResponse
    {
        $this->authorize('approveCorrection', Attendance::class);

        $user = $request->user();
        $tenantId = (int) $user->tenant_id;
        $today = TenantTime::today($tenantId);

        $data = $request->validate([
            'status' => ['required', Rule::in(HrAttendanceCorrection::STATUSES)],
            'clock_in' => ['nullable', 'required_if:status,'.HrAttendanceCorrection::STATUS_PRESENT, 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            'select_all' => ['nullable', 'boolean'],
            'items' => ['required_unless:select_all,true', 'array', 'max:'.self::BULK_CORRECTION_LIMIT],
            'items.*.employee_id' => ['required', 'integer'],
            'items.*.date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'filters' => ['nullable', 'array'],
            'filters.date_from' => ['required_if:select_all,true', 'nullable', 'date_format:Y-m-d'],
            'filters.date_to' => ['required_if:select_all,true', 'nullable', 'date_format:Y-m-d'],
            // Every filter the rekap applies must be listed here: validate()
            // drops unlisted keys, and a lost search would widen "pilih semua"
            // from one employee to the whole day.
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.status' => ['nullable', 'string', 'max:50'],
            'filters.branch_id' => ['nullable', 'integer'],
        ], [
            'clock_in.required_if' => 'Jam masuk wajib diisi untuk status Hadir.',
            'clock_in.date_format' => 'Format jam masuk harus HH:MM.',
            'clock_out.date_format' => 'Format jam pulang harus HH:MM.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'items.required_unless' => 'Pilih minimal satu baris.',
            'items.max' => 'Maksimal '.self::BULK_CORRECTION_LIMIT.' baris sekali koreksi.',
            'items.*.date.before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
        ]);

        $targets = ($data['select_all'] ?? false)
            ? $this->filteredTargets($user, $tenantId, $today, $data['filters'] ?? [])
            : collect($data['items'])->map(fn (array $item): array => [
                'employee_id' => (int) $item['employee_id'],
                'date' => $item['date'],
            ]);

        $targets = $targets->unique(fn (array $target): string => $target['employee_id'].'|'.$target['date'])->values();

        if ($targets->isEmpty()) {
            return back()->withErrors(['items' => 'Tidak ada baris yang bisa dikoreksi.']);
        }

        if ($targets->count() > self::BULK_CORRECTION_LIMIT) {
            return back()->withErrors(['items' => 'Ada '.$targets->count().' baris; maksimal '.self::BULK_CORRECTION_LIMIT.' sekali koreksi. Persempit filternya.']);
        }

        // The branch fence again, for every employee named: an id outside the
        // viewer's branches fails the whole batch rather than slipping in.
        $employeeQuery = Employee::forTenant($tenantId)->whereKey($targets->pluck('employee_id')->unique()->all());
        $this->applyBranchScope($employeeQuery, $user);
        $employees = $employeeQuery->get()->keyBy('id');

        abort_if($targets->pluck('employee_id')->unique()->diff($employees->keys())->isNotEmpty(), 404);

        DB::transaction(function () use ($targets, $employees, $data, $user): void {
            foreach ($targets as $target) {
                HrAttendanceCorrection::apply(
                    $employees[$target['employee_id']],
                    $target['date'],
                    $data['status'],
                    $data['clock_in'] ?? null,
                    $data['clock_out'] ?? null,
                    $data['reason'],
                    $user,
                );
            }
        });

        $response = back()->with('success', $targets->count().' baris absensi dikoreksi.');

        $locked = $this->lockedPeriodCodes($tenantId, $targets->pluck('date')->unique()->all());

        if ($locked !== []) {
            $response->with('warning', 'Periode payroll '.implode(', ', $locked).' sudah dikunci, jadi gaji di periode itu tidak berubah otomatis. Selisihnya dicatat lewat Payroll → Koreksi Gaji.');
        }

        return $response;
    }

    /**
     * Every (employee, date) the rekap shows for the posted filters — stored
     * rows plus, on a single day, the "belum absen / belum tercatat" rows.
     *
     * @param  array<string, mixed>  $source
     * @return Collection<int, array{employee_id: int, date: string}>
     */
    private function filteredTargets(User $user, int $tenantId, string $today, array $source): Collection
    {
        $from = (string) ($source['date_from'] ?? $today);
        $to = (string) ($source['date_to'] ?? $from);
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $to = min($to, $today);

        if ($from > $to) {
            return collect();
        }

        $filters = $this->rekapFilters($source);

        $targets = $this->rekapQuery($user, $tenantId, $from, $to, $filters)
            ->get(['employee_id', 'date'])
            ->map(fn (Attendance $attendance): array => [
                'employee_id' => (int) $attendance->employee_id,
                'date' => $attendance->date->format('Y-m-d'),
            ]);

        if ($from === $to && in_array($filters['status'], [null, self::NOT_YET_IN_STATUS], true)) {
            $search = mb_strtolower((string) $filters['search']);

            $missing = collect($this->notYetIn($user, $tenantId, $from, $filters['branch_id']))
                ->filter(fn (array $employee): bool => $search === ''
                    || str_contains(mb_strtolower($employee['name']), $search)
                    || str_contains(mb_strtolower((string) $employee['employee_number']), $search))
                ->map(fn (array $employee): array => ['employee_id' => $employee['id'], 'date' => $from]);

            $targets = $targets->concat($missing);
        }

        return $targets->values();
    }

    /**
     * Codes of the locked payroll periods covering any of the dates.
     *
     * @param  array<int, string>  $dates
     * @return list<string>
     */
    private function lockedPeriodCodes(int $tenantId, array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        return PayrollPeriod::forTenant($tenantId)
            ->where('status', 'locked')
            ->whereDate('start_date', '<=', max($dates))
            ->whereDate('end_date', '>=', min($dates))
            ->get(['code', 'start_date', 'end_date'])
            ->filter(fn (PayrollPeriod $period): bool => collect($dates)->contains(
                fn (string $date): bool => $period->start_date->toDateString() <= $date && $period->end_date->toDateString() >= $date,
            ))
            ->pluck('code')
            ->values()
            ->all();
    }

    /**
     * The day's attendance rows followed by one row per no-show ("Belum
     * Absen" today, "Belum Tercatat" on a past day not yet closed as Alpa),
     * paginated in memory in the same shape the resource paginator sends.
     *
     * The virtual rows carry a negative id (the employee's, negated) so they
     * never collide with an attendance id, and no coordinates, so the table
     * does not open a detail view for a record that does not exist.
     *
     * @param  Collection<int, Attendance>  $records
     * @param  array<int, array{id: int, name: string, employee_number: string|null, branch: string|null}>  $notYetIn
     * @return array{data: list<array<string, mixed>>, meta: array<string, int|null>, links: array<string, null>}
     */
    private function withNotYetInRows(Request $request, Collection $records, array $notYetIn, string $date, string $label): array
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $day = Carbon::parse($date);

        $virtual = collect($notYetIn)
            ->filter(fn (array $employee): bool => $search === ''
                || str_contains(mb_strtolower($employee['name']), $search)
                || str_contains(mb_strtolower((string) $employee['employee_number']), $search))
            ->map(fn (array $employee): array => [
                'id' => -$employee['id'],
                'employee' => [
                    'id' => $employee['id'],
                    'name' => $employee['name'],
                    'employee_number' => $employee['employee_number'],
                    'initials' => $this->initials($employee['name']),
                    'avatar_color' => $this->avatarColor($employee['name']),
                ],
                'shift' => null,
                'date' => $day->format('d M Y'),
                'date_raw' => $date,
                'clock_in' => '—',
                'clock_out' => '—',
                'late_minutes' => 0,
                'telat' => '—',
                'status' => self::NOT_YET_IN_STATUS,
                'status_label' => $label,
                'location_status' => null,
                'clock_in_coords' => null,
                'clock_out_coords' => null,
                'work_location' => null,
                'distance_meter' => null,
            ]);

        $rows = collect(AttendanceResource::collection($records)->resolve($request))
            ->concat($virtual)
            ->values();

        $perPage = max(1, $request->integer('per_page', 10));
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($lastPage, max(1, $request->integer('page', 1)));
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $slice->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $slice->isEmpty() ? null : ($page - 1) * $perPage + 1,
                'to' => $slice->isEmpty() ? null : ($page - 1) * $perPage + $slice->count(),
            ],
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
        ];
    }

    /**
     * Who was due at work on the date and has no attendance row for it.
     *
     * Today that is "belum absen" — the day is still open. On a past day it is
     * a no-show the nightly marker has not closed yet (a day from before the
     * marker existed, or before a backfill): listed so HR can see it and
     * decide, row by row, whether it was Alpa, leave, or a forgotten punch.
     *
     * @return array<int, array{id: int, name: string, employee_number: string|null, branch: string|null}>
     */
    private function notYetIn(User $user, int $tenantId, string $date, ?int $branchId): array
    {
        $missing = AbsenceMarker::missing($tenantId, Carbon::parse($date), function ($query) use ($user, $branchId): void {
            $query->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId));
            $this->applyBranchScope($query, $user);
        });

        $branchNames = Branch::query()->whereKey($missing->pluck('branch_id')->filter()->unique())->pluck('name', 'id');

        return $missing
            ->sortBy('full_name')
            ->map(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'name' => (string) $employee->full_name,
                'employee_number' => $employee->employee_number,
                'branch' => $employee->branch_id !== null ? ($branchNames[$employee->branch_id] ?? null) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Live attendance monitor: a map of today's clock-in GPS points across
     * branches, presence KPIs, and a recent-activity feed.
     */
    public function monitor(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        // Date range: `date_from`..`date_to`. A lone `date` sets both ends
        // (keeps the single-day URLs and older callers working). The range is
        // normalised so `from` never comes after `to`.
        $single = $request->query('date');
        $from = $this->resolveDate($request->query('date_from') ?? $single, $tenantId);
        $to = $this->resolveDate($request->query('date_to') ?? $single, $tenantId);
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        $fromString = $from->format('Y-m-d');
        $toString = $to->format('Y-m-d');
        $isRange = $fromString !== $toString;

        $branchId = $request->query('branch_id');
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        $query = Attendance::query()
            ->forTenant($tenantId)
            ->whereDate('date', '>=', $fromString)
            ->whereDate('date', '<=', $toString)
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->with([
                'employee:id,full_name,employee_number,branch_id',
                'employee.branch:id,name',
                'workLocation:id,name',
            ]);

        $this->applyBranchScope($query, $request->user());

        $records = $query->orderByDesc('clock_in_at')->get();

        $points = $records
            ->filter(fn (Attendance $a): bool => $a->clock_in_lat !== null && $a->clock_in_lng !== null)
            ->map(fn (Attendance $a): array => [
                'lat' => (float) $a->clock_in_lat,
                'lng' => (float) $a->clock_in_lng,
                'label' => trim(($a->employee?->full_name ?? 'Karyawan').' · '.($a->employee?->branch?->name ?? 'Tanpa Cabang')),
                'status' => (string) $a->status,
            ])
            ->values()
            ->all();

        $activity = $records
            ->take(15)
            ->map(function (Attendance $a) use ($isRange): array {
                $workLocation = $a->workLocation?->name;
                $branch = $a->employee?->branch?->name;

                return [
                    'id' => $a->id,
                    'name' => (string) ($a->employee?->full_name ?? 'Karyawan'),
                    'location' => (string) ($workLocation ?? $branch ?? '—'),
                    // Only when the branch isn't already standing in as the
                    // location above, so it never renders twice in one row.
                    'branch' => $workLocation !== null ? $branch : null,
                    'time' => $a->clock_in_at?->format('H:i'),
                    // Day tag, shown only when the feed spans multiple days.
                    'date' => $isRange ? $a->date?->format('d M') : null,
                    'status' => (string) $a->status,
                    'status_label' => self::STATUS_LABELS[$a->status] ?? ucfirst((string) $a->status),
                ];
            })
            ->values()
            ->all();

        // On-time / late are cumulative check-in counts across the range; the
        // no-show is the active head-count minus everyone who checked in or was
        // on leave at least once (distinct employees, so a range never double
        // counts and a single day still matches one row per person).
        $onTime = $records->where('status', 'present')->count();
        $late = $records->where('status', 'late')->count();
        $checkedInIds = $records
            ->whereIn('status', ['present', 'late'])
            ->pluck('employee_id')
            ->unique();
        $leaveIds = $records
            ->where('status', 'leave')
            ->pluck('employee_id')
            ->unique()
            ->diff($checkedInIds);

        $totalPersonnel = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->count();
        $noShow = max($totalPersonnel - $checkedInIds->count() - $leaveIds->count(), 0);

        return Inertia::render('avana/absensi/monitor', [
            'range' => [
                'from' => $fromString,
                'to' => $toString,
                'display' => $isRange
                    ? $from->format('d M').' – '.$to->format('d M Y')
                    : $from->format('d M Y'),
            ],
            'filters' => [
                'date_from' => $fromString,
                'date_to' => $toString,
                'branch_id' => $branchId,
            ],
            'branches' => Branch::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'kpis' => [
                'total_personnel' => $totalPersonnel,
                'on_time' => $onTime,
                'late' => $late,
                'no_show' => $noShow,
            ],
            'points' => $points,
            'activity' => $activity,
        ]);
    }

    /**
     * Show a single attendance record: employee, clock in/out, selfies, and the
     * clock-in point against the work-location geofence.
     */
    public function show(Request $request, Attendance $attendance): Response
    {
        $this->authorize('viewAny', Attendance::class);

        abort_if((int) $attendance->tenant_id !== (int) $request->user()->tenant_id, 404);

        $this->assertBranchVisible($request, $attendance);

        $attendance->load([
            'employee:id,full_name,employee_number,branch_id,department_id,position_id',
            'employee.department:id,name',
            'employee.position:id,name',
            'branch:id,name',
            'shift:id,name,start_time,end_time',
            'workLocation:id,name,latitude,longitude,radius_meter,address',
            'selfies',
        ]);

        return Inertia::render('avana/absensi/show', [
            'attendance' => $this->detailPayload($attendance),
        ]);
    }

    /**
     * Permanently delete an attendance record along with its selfie photos —
     * both the rows and the underlying files (the FK only nulls on delete).
     */
    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        abort_if((int) $attendance->tenant_id !== (int) $request->user()->tenant_id, 404);

        $this->authorize('delete', $attendance);

        $this->assertBranchVisible($request, $attendance);

        foreach ($attendance->selfies as $selfie) {
            if ($selfie->file_path !== null) {
                PrivateFile::delete($selfie->file_path);
            }

            $selfie->delete();
        }

        $attendance->delete();

        return redirect()
            ->route('avana.absensi')
            ->with('success', 'Data absensi dan foto selfie dihapus.');
    }

    /**
     * Build the rich detail payload for the attendance show page.
     *
     * @return array<string, mixed>
     */
    private function detailPayload(Attendance $attendance): array
    {
        $location = $attendance->workLocation;
        $distance = null;

        if ($location !== null && $attendance->clock_in_lat !== null && $attendance->clock_in_lng !== null) {
            $meters = $location->distanceMeters((float) $attendance->clock_in_lat, (float) $attendance->clock_in_lng);
            $distance = $meters === null ? null : (int) round($meters);
        }

        // Selfies are stored one row per punch with no type column, so the
        // capture order is what tells the clock-in shot from the clock-out one.
        $shots = $attendance->selfies
            ->sortBy(fn ($selfie): int => $selfie->captured_at?->getTimestamp() ?? 0)
            ->values();

        $selfies = $shots
            ->map(fn ($selfie): array => [
                'url' => PrivateFile::urlFor($selfie->file_path),
                'captured_at' => $selfie->captured_at?->format('d M Y H:i'),
                'coords' => $this->coords($selfie->latitude, $selfie->longitude),
            ])
            ->all();

        $clockOutPhoto = $shots->count() > 1 && $attendance->clock_out_at !== null
            ? PrivateFile::urlFor($shots->last()->file_path)
            : null;

        $employee = $attendance->employee;

        return [
            'id' => $attendance->id,
            'date' => $attendance->date?->format('d M Y'),
            'date_raw' => $attendance->date?->format('Y-m-d'),
            'status' => $attendance->status,
            'status_label' => self::STATUS_LABELS[$attendance->status] ?? $attendance->status,
            'location_status' => $attendance->location_status,
            'late_minutes' => (int) $attendance->late_minutes,
            'work_minutes' => (int) $attendance->work_minutes,
            'distance_meter' => $distance,
            'employee' => $employee === null ? null : [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
            ],
            'branch' => $attendance->branch?->name,
            'shift' => $attendance->shift === null ? null : [
                'name' => $attendance->shift->name,
                'start_time' => $attendance->shift->start_time,
                'end_time' => $attendance->shift->end_time,
            ],
            'clock_in' => [
                'time' => $attendance->clock_in_at?->format('H:i'),
                'coords' => $this->coords($attendance->clock_in_lat, $attendance->clock_in_lng),
                'photo_url' => $selfies[0]['url'] ?? null,
            ],
            'clock_out' => [
                'time' => $attendance->clock_out_at?->format('H:i'),
                'coords' => $this->coords($attendance->clock_out_lat, $attendance->clock_out_lng),
                'photo_url' => $clockOutPhoto,
            ],
            'work_location' => $location === null ? null : [
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude === null ? null : (float) $location->latitude,
                'longitude' => $location->longitude === null ? null : (float) $location->longitude,
                'radius_meter' => (int) ($location->radius_meter ?? 0),
            ],
            'selfies' => $selfies,
        ];
    }

    /**
     * Compact {lat, lng} float pair, or null when either component is unset.
     *
     * @return array{lat: float, lng: float}|null
     */
    private function coords(mixed $lat, mixed $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /**
     * Abort with 404 when a branch/own-scoped user views another scope's record.
     */
    private function assertBranchVisible(Request $request, Attendance $attendance): void
    {
        $user = $request->user();
        $scope = $this->effectiveScope($user);

        if ($scope === 'branch') {
            abort_if(! in_array((int) $attendance->branch_id, $this->scopedBranchIds($user), true), 404);
        } elseif ($scope === 'own') {
            abort_if((int) $attendance->employee_id !== (int) ($user->employee?->id ?? 0), 404);
        }
    }

    /**
     * Build up to two uppercase initials from a full name.
     */
    private function initials(?string $fullName): string
    {
        $words = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        $initials = collect($words)
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a deterministic avatar color derived from the employee name.
     */
    private function avatarColor(?string $fullName): string
    {
        return self::AVATAR_PALETTE[crc32((string) $fullName) % count(self::AVATAR_PALETTE)];
    }

    /**
     * Resolve the requested date (Y-m-d), defaulting to today when absent or invalid.
     *
     * "Today" is the tenant's own, not the server's: a Makassar office opening
     * the screen at 00:30 was shown the previous day's attendance, because the
     * server was still an hour behind them.
     */
    private function resolveDate(?string $input, int|string|null $tenantId): Carbon
    {
        if (is_string($input) && $input !== '') {
            try {
                return Carbon::createFromFormat('Y-m-d', $input)->startOfDay();
            } catch (\Throwable) {
                return TenantTime::startOfToday($tenantId);
            }
        }

        return TenantTime::startOfToday($tenantId);
    }
}
