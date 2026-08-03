<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Models\WfhRequest;
use App\Services\ApprovalEngine;
use App\Services\AttendanceCorrectionApproval;
use App\Services\AutoApproval;
use App\Services\LeaveApproval;
use App\Support\PendingApprover;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
    use AppliesBranchScope;
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
     * Roles whose remit is the whole tenant, not one team.
     *
     * `manager` is deliberately absent: managing a team is not managing the
     * company, and the screen has to stop showing one manager another's
     * people.
     *
     * @var array<int, string>
     */
    private const COMPANY_WIDE_ROLES = ['super_admin', 'admin_tenant_hr'];

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

        $pendingItems = $this->collectItems($request, $tenantId, ['pending'])
            ->sortByDesc('sort_ts')
            ->values();

        $historyItems = $this->collectItems($request, $tenantId, ['approved', 'rejected'])
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

        return Inertia::render('avana/approval/index', [
            'pending' => $pendingItems->map(fn (array $item): array => Arr::except($item, 'sort_ts'))->all(),
            'history' => $historyItems->map(fn (array $item): array => Arr::except($item, 'sort_ts'))->all(),
            'counts' => $counts,
        ]);
    }

    /**
     * Approve a pending request resolved by its `type` tag and id.
     *
     * Routed through {@see ApprovalEngine} first: when the tenant configured a
     * multi-step workflow for this request type, approving here advances one
     * step rather than finalizing. Deciding directly used to skip every
     * remaining step, strand the `approval_requests` row on step 1, and leave no
     * approval log — the configured flow was silently void from this screen.
     * Requests with no workflow instance fall through to the direct finalize.
     */
    public function approve(Request $request, string $type, int $id): RedirectResponse
    {
        $this->ensureCanApprove($request);

        $model = $this->resolveModel($request, $type, $id);

        if (! ApprovalEngine::decide($model, $request->user()->id, 'approve')) {
            $this->finalizeApproved($model, (int) $request->user()->id);
        }

        // A multi-step workflow only advanced here; do not claim it is done.
        return back()->with('success', $model->fresh()?->getAttribute('status') === 'approved'
            ? self::MESSAGES[$type]['approve']
            : 'Persetujuan tercatat, menunggu tahap berikutnya');
    }

    /**
     * Reject a pending request resolved by its `type` tag and id.
     *
     * A rejection ends the workflow at whatever step it sits on, so the engine
     * closes its instance and logs the decision before the request is marked.
     */
    public function reject(Request $request, string $type, int $id): RedirectResponse
    {
        $this->ensureCanApprove($request);

        $model = $this->resolveModel($request, $type, $id);

        if (! ApprovalEngine::decide($model, $request->user()->id, 'reject')) {
            $model->update(['status' => 'rejected']);

            if ($model instanceof AttendanceCorrection) {
                $model->update(['approver_id' => $request->user()->id]);
            }
        }

        return back()->with('success', self::MESSAGES[$type]['reject']);
    }

    /**
     * Approve a request that is not workflow-driven, through the same services
     * the module screens and the engine's own finalize step use — so a leave
     * approved here draws down the right balance (a sub-type charges its parent
     * quota) and a correction is actually written onto the attendance row.
     */
    private function finalizeApproved(Model $model, int $approverUserId): void
    {
        match (true) {
            $model instanceof LeaveRequest => LeaveApproval::finalize($model, $approverUserId),
            $model instanceof OvertimeRequest => AutoApproval::overtime($model),
            $model instanceof WfhRequest => AutoApproval::wfh($model),
            $model instanceof AttendanceCorrection => AttendanceCorrectionApproval::finalize($model, $approverUserId),
            default => $model->update(['status' => 'approved']),
        };
    }

    /**
     * Aggregate request rows of the given statuses across every type into the
     * shared item shape, tagged with a sortable `sort_ts` timestamp.
     *
     * @param  array<int, string>  $statuses
     * @return Collection<int, array<string, mixed>>
     */
    private function collectItems(Request $request, int $tenantId, array $statuses): Collection
    {
        return collect(self::TYPE_MODELS)
            ->flatMap(fn (string $modelClass, string $type): Collection => $this->itemsForType($request, $type, $modelClass, $tenantId, $statuses));
    }

    /**
     * Fetch and shape a single request type for the given statuses.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $statuses
     * @return Collection<int, array<string, mixed>>
     */
    private function itemsForType(Request $request, string $type, string $modelClass, int $tenantId, array $statuses): Collection
    {
        $query = $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', $statuses)
            ->with('employee:id,full_name,employee_number,branch_id');

        $this->scopeToApprover($query, $request->user());

        if ($type === 'leave') {
            $query->with('leaveType:id,name');
        }

        return $query->latest('created_at')
            ->get()
            ->map(fn (Model $model): array => $this->mapItem($model, $type));
    }

    /**
     * Narrow a request query to what this approver is entitled to see.
     *
     * The screen used to answer "every pending request in the tenant" to
     * anyone who could reach it, so a branch manager in Surabaya read — and
     * could decide — a Medan employee's leave. Nothing about approving one
     * team's requests implies the right to read another's.
     *
     * Company-wide roles keep the whole tenant, which is the job: HR chases
     * requests nobody else has picked up. Everyone else sees the requests
     * routed to them and their own reports', on top of whatever branch scope
     * the account already carries.
     *
     * @param  Builder<Model>  $query
     */
    private function scopeToApprover(Builder $query, User $user): void
    {
        $user->loadMissing('roles');

        if ($user->roles->whereIn('code', self::COMPANY_WIDE_ROLES)->isNotEmpty()) {
            $this->applyBranchScopeViaEmployee($query, $user);

            return;
        }

        $employeeId = Employee::forTenant($user->tenant_id)
            ->where('user_id', $user->id)
            ->value('id');

        // No employee record means no reports and nothing routed here, so the
        // list is empty rather than everyone's.
        $query->where(function (Builder $scoped) use ($employeeId): void {
            $scoped->where('current_approver_id', $employeeId ?? 0)
                ->orWhereHas('employee', fn (Builder $employee) => $employee->where('manager_id', $employeeId ?? 0));
        });

        $this->applyBranchScopeViaEmployee($query, $user);
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
     *
     * Gated on the same scope the list is built from, so a request the user
     * cannot see is also one they cannot decide by posting its id.
     */
    private function resolveModel(Request $request, string $type, int $id): Model
    {
        $modelClass = self::TYPE_MODELS[$type] ?? null;

        abort_if($modelClass === null, 404);

        $model = $modelClass::query()->findOrFail($id);

        abort_if((int) $model->tenant_id !== (int) $request->user()->tenant_id, 404);

        $visible = $modelClass::query()->whereKey($id);
        $this->scopeToApprover($visible, $request->user());

        abort_unless($visible->exists(), 404);

        return $model;
    }

    /**
     * Abort with 403 unless the user is an approver role or holds an approval
     * permission (any explicit code or a team.*.approve permission).
     */
    private function ensureCanApprove(Request $request): void
    {
        abort_unless($this->canApprove($request), 403);
    }

    /**
     * Whether this user may act on approvals at all.
     *
     * A workflow may route a request to someone who holds no approval
     * permission — "atasan langsung" names whoever the employee reports to,
     * not whoever HR granted a module to. Refusing them here would leave the
     * request stranded on a step its own approver cannot reach, so anything
     * currently waiting on them counts as licence.
     */
    private function canApprove(Request $request): bool
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

        return $isPrivileged || $hasApprovePermission || PendingApprover::awaits($user);
    }
}
