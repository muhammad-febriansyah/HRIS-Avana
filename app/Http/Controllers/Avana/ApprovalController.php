<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Models\WfhRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    use AuthorizesRequests;

    /**
     * Map of the request `type` tag to its backing Eloquent model.
     *
     * @var array<string, class-string<Model>>
     */
    private const TYPE_MODELS = [
        'leave' => LeaveRequest::class,
        'lembur' => OvertimeRequest::class,
        'izin' => PermissionRequest::class,
        'wfh' => WfhRequest::class,
        'koreksi' => AttendanceCorrection::class,
    ];

    /**
     * Roles that may approve any request within their tenant.
     *
     * @var array<int, string>
     */
    private const APPROVER_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Explicit permission codes that grant approval access.
     *
     * @var array<int, string>
     */
    private const APPROVE_PERMISSIONS = [
        'leave.approve',
        'overtime.approve',
        'wfh.approve',
        'attendance.correction.approve',
    ];

    /**
     * Indonesian labels for the status enum (pending/approved/rejected).
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    /**
     * Indonesian success messages per request type for approve / reject.
     *
     * @var array<string, array{approve: string, reject: string}>
     */
    private const MESSAGES = [
        'leave' => ['approve' => 'Cuti disetujui', 'reject' => 'Cuti ditolak'],
        'lembur' => ['approve' => 'Lembur disetujui', 'reject' => 'Lembur ditolak'],
        'izin' => ['approve' => 'Izin disetujui', 'reject' => 'Izin ditolak'],
        'wfh' => ['approve' => 'WFH disetujui', 'reject' => 'WFH ditolak'],
        'koreksi' => ['approve' => 'Koreksi absensi disetujui', 'reject' => 'Koreksi absensi ditolak'],
    ];

    /**
     * Deterministic avatar background palette (mirrors LeaveRequestResource).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Render the unified approval center: pending requests aggregated across
     * every request module, recent history, and per-type pending counts.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanApprove($request);

        $tenantId = (int) $request->user()->tenant_id;

        $pendingItems = $this->collectItems($tenantId, ['pending'])
            ->sortByDesc('sort_ts')
            ->values();

        $historyItems = $this->collectItems($tenantId, ['approved', 'rejected'])
            ->sortByDesc('sort_ts')
            ->take(30)
            ->values();

        $counts = [
            'leave' => $pendingItems->where('type', 'leave')->count(),
            'lembur' => $pendingItems->where('type', 'lembur')->count(),
            'izin' => $pendingItems->where('type', 'izin')->count(),
            'wfh' => $pendingItems->where('type', 'wfh')->count(),
            'koreksi' => $pendingItems->where('type', 'koreksi')->count(),
            'total' => $pendingItems->count(),
        ];

        return Inertia::render('avana/approval', [
            'pending' => $pendingItems->map(fn (array $item): array => Arr::except($item, 'sort_ts'))->all(),
            'history' => $historyItems->map(fn (array $item): array => Arr::except($item, 'sort_ts'))->all(),
            'counts' => $counts,
        ]);
    }

    /**
     * Approve a pending request resolved by its `type` tag and id.
     */
    public function approve(Request $request, string $type, int $id): RedirectResponse
    {
        $this->ensureCanApprove($request);

        $model = $this->resolveModel($request, $type, $id);

        $model->update(['status' => 'approved']);

        if ($model instanceof LeaveRequest) {
            $this->decrementLeaveBalance($model);
        }

        if ($model instanceof AttendanceCorrection) {
            $model->update(['approver_id' => $request->user()->id]);
        }

        return back()->with('success', self::MESSAGES[$type]['approve']);
    }

    /**
     * Reject a pending request resolved by its `type` tag and id.
     */
    public function reject(Request $request, string $type, int $id): RedirectResponse
    {
        $this->ensureCanApprove($request);

        $model = $this->resolveModel($request, $type, $id);

        $model->update(['status' => 'rejected']);

        if ($model instanceof AttendanceCorrection) {
            $model->update(['approver_id' => $request->user()->id]);
        }

        return back()->with('success', self::MESSAGES[$type]['reject']);
    }

    /**
     * Aggregate request rows of the given statuses across every type into the
     * shared item shape, tagged with a sortable `sort_ts` timestamp.
     *
     * @param  array<int, string>  $statuses
     * @return Collection<int, array<string, mixed>>
     */
    private function collectItems(int $tenantId, array $statuses): Collection
    {
        return collect(self::TYPE_MODELS)
            ->flatMap(fn (string $modelClass, string $type): Collection => $this->itemsForType($type, $modelClass, $tenantId, $statuses));
    }

    /**
     * Fetch and shape a single request type for the given statuses.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $statuses
     * @return Collection<int, array<string, mixed>>
     */
    private function itemsForType(string $type, string $modelClass, int $tenantId, array $statuses): Collection
    {
        $query = $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $statuses)
            ->with('employee:id,full_name,employee_number,branch_id');

        if ($type === 'leave') {
            $query->with('leaveType:id,name');
        }

        return $query->latest('created_at')
            ->get()
            ->map(fn (Model $model): array => $this->mapItem($model, $type));
    }

    /**
     * Build the unified approval item shape for a single request model.
     *
     * @return array<string, mixed>
     */
    private function mapItem(Model $model, string $type): array
    {
        return [
            'type' => $type,
            'id' => $model->id,
            'employee' => $this->shapeEmployee($model),
            'title' => $this->titleFor($model, $type),
            'detail' => $this->detailFor($model, $type),
            'reason' => $model->reason,
            'requested_at' => $model->created_at?->format('d M Y H:i'),
            'status' => $model->status,
            'status_label' => self::STATUS_LABELS[$model->status] ?? $model->status,
            'sort_ts' => $model->created_at?->getTimestamp() ?? 0,
        ];
    }

    /**
     * The human-readable headline for a request (leave type, "Lembur 3 jam", …).
     */
    private function titleFor(Model $model, string $type): string
    {
        return match ($type) {
            'leave' => $model->leaveType?->name ?? 'Cuti',
            'lembur' => 'Lembur '.(float) $model->hours.' jam',
            'izin' => $model->type === 'keluar_kantor' ? 'Keluar Kantor' : 'Izin Jam',
            'wfh' => 'WFH',
            'koreksi' => Str::title(str_replace('_', ' ', (string) $model->correction_type)),
            default => Str::title($type),
        };
    }

    /**
     * The secondary detail line for a request (date range / time / hours).
     */
    private function detailFor(Model $model, string $type): string
    {
        return match ($type) {
            'leave', 'wfh' => $this->dateRange($model->start_date, $model->end_date),
            'lembur' => $model->date?->format('d M Y') ?? '—',
            'izin' => $this->izinDetail($model),
            'koreksi' => $this->koreksiDetail($model),
            default => '—',
        };
    }

    /**
     * Format a start–end date range, collapsing single-day ranges.
     */
    private function dateRange(?CarbonInterface $start, ?CarbonInterface $end): string
    {
        if ($start === null) {
            return '—';
        }

        $startLabel = $start->format('d M Y');

        if ($end === null || $end->isSameDay($start)) {
            return $startLabel;
        }

        return $startLabel.' – '.$end->format('d M Y');
    }

    /**
     * Format the date and optional time window for a permission (izin) request.
     */
    private function izinDetail(Model $model): string
    {
        $date = $model->date?->format('d M Y') ?? '—';
        $start = $this->shortTime($model->start_time);
        $end = $this->shortTime($model->end_time);

        if ($start !== null && $end !== null) {
            return $date.' · '.$start.'–'.$end;
        }

        return $date;
    }

    /**
     * Format the date and requested clock times for a correction request.
     */
    private function koreksiDetail(Model $model): string
    {
        $date = $model->date?->format('d M Y') ?? '—';
        $times = array_filter([
            $this->shortTime($model->requested_clock_in),
            $this->shortTime($model->requested_clock_out),
        ]);

        if ($times !== []) {
            return $date.' · '.implode(' – ', $times);
        }

        return $date;
    }

    /**
     * Trim a `HH:MM:SS` time string down to `HH:MM`, or null when empty.
     */
    private function shortTime(?string $time): ?string
    {
        return ($time === null || $time === '') ? null : substr($time, 0, 5);
    }

    /**
     * Shape the eager-loaded employee for a request row, deriving initials/color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Model $model): ?array
    {
        $employee = $model->employee;

        if ($employee === null) {
            return null;
        }

        return [
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'initials' => $this->initials($employee->full_name),
            'avatar_color' => $this->avatarColor($employee->full_name),
        ];
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
        $index = crc32((string) $fullName) % count(self::AVATAR_PALETTE);

        return self::AVATAR_PALETTE[$index];
    }

    /**
     * Resolve a request model by its type tag, enforcing tenant ownership.
     */
    private function resolveModel(Request $request, string $type, int $id): Model
    {
        $modelClass = self::TYPE_MODELS[$type] ?? null;

        abort_if($modelClass === null, 404);

        $model = $modelClass::query()->findOrFail($id);

        abort_if((int) $model->tenant_id !== (int) $request->user()->tenant_id, 404);

        return $model;
    }

    /**
     * Decrement the matching leave balance after a leave approval.
     */
    private function decrementLeaveBalance(LeaveRequest $leave): void
    {
        $balance = LeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', $leave->start_date->year)
            ->first();

        if ($balance !== null) {
            $balance->update([
                'used' => $balance->used + $leave->total_days,
                'remaining' => max(0, $balance->remaining - $leave->total_days),
            ]);
        }
    }

    /**
     * Abort with 403 unless the user is an approver role or holds an approval
     * permission (any explicit code or a team.*.approve permission).
     */
    private function ensureCanApprove(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::APPROVER_ROLES)->isNotEmpty();

        $hasApprovePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => in_array($code, self::APPROVE_PERMISSIONS, true)
                || (str_starts_with($code, 'team.') && str_ends_with($code, '.approve')));

        abort_unless($isPrivileged || $hasApprovePermission, 403);
    }
}
