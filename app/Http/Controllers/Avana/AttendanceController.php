<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\Avana\AttendanceResource;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\ApprovalEngine;
use App\Services\AttendanceCorrectionApproval;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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

        $date = $this->resolveDate($request->query('date'));
        $dateString = $date->format('Y-m-d');

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = Attendance::query()
            ->forTenant($tenantId)
            ->whereDate('date', $dateString)
            ->with([
                'employee:id,full_name,employee_number,branch_id',
                'employee.branch:id,name',
                'shift:id,name,start_time,end_time',
                'workLocation:id,name,latitude,longitude,radius_meter',
            ])
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('branch_id'), fn ($q, $branchId) => $q->where('branch_id', $branchId));

        $this->applyBranchScope($query, $request->user());

        $attendances = $query
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        $statusCountQuery = Attendance::forTenant($tenantId)
            ->whereDate('date', $dateString);

        $this->applyBranchScope($statusCountQuery, $request->user());

        $statusCounts = $statusCountQuery
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return Inertia::render('avana/absensi/index', [
            'attendances' => AttendanceResource::collection($attendances),
            'filters' => array_merge(
                $request->only(['search', 'status', 'branch_id', 'sort', 'direction', 'per_page']),
                ['date' => $dateString],
            ),
            'date' => [
                'value' => $dateString,
                'display' => $date->format('d M Y'),
            ],
            'kpis' => [
                'hadir' => (int) ($statusCounts['present'] ?? 0),
                'terlambat' => (int) ($statusCounts['late'] ?? 0),
                'izin' => (int) ($statusCounts['leave'] ?? 0),
                'alpa' => (int) ($statusCounts['absent'] ?? 0),
            ],
            'branches' => Branch::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
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
        $from = $this->resolveDate($request->query('date_from') ?? $single);
        $to = $this->resolveDate($request->query('date_to') ?? $single);
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
                Storage::disk('public')->delete($selfie->file_path);
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

        $selfies = $attendance->selfies
            ->map(fn ($selfie): array => [
                'url' => Storage::disk('public')->url($selfie->file_path),
                'captured_at' => $selfie->captured_at?->format('d M Y H:i'),
                'coords' => $this->coords($selfie->latitude, $selfie->longitude),
            ])
            ->values()
            ->all();

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
     * Approve a pending attendance correction and sync the linked attendance.
     */
    public function approveCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $correction);
        $this->authorize('approveCorrection', Attendance::class);

        // A workflow instance advances step-by-step; without one, approve here
        // writes the correction to the attendance record directly.
        if (! ApprovalEngine::decide($correction, $request->user()->id, 'approve')) {
            AttendanceCorrectionApproval::finalize($correction, $request->user()->id);
        }

        return back()->with('success', 'Koreksi absensi disetujui');
    }

    /**
     * Reject a pending attendance correction.
     */
    public function rejectCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $correction);
        $this->authorize('rejectCorrection', Attendance::class);

        if (! ApprovalEngine::decide($correction, $request->user()->id, 'reject')) {
            $correction->update([
                'status' => 'rejected',
                'approver_id' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Koreksi absensi ditolak');
    }

    /**
     * Resolve the requested date (Y-m-d), defaulting to today when absent or invalid.
     */
    private function resolveDate(?string $input): Carbon
    {
        if (is_string($input) && $input !== '') {
            try {
                return Carbon::createFromFormat('Y-m-d', $input)->startOfDay();
            } catch (\Throwable) {
                return Carbon::today();
            }
        }

        return Carbon::today();
    }

    /**
     * Abort with 404 when the correction does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, AttendanceCorrection $correction): void
    {
        abort_if((int) $correction->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
