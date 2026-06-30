<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\StoreLeaveRequest;
use App\Http\Resources\Avana\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\WfhRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    /**
     * Sortable columns whitelist for the index DataTable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['start_date', 'created_at'];

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
     * Deterministic avatar background palette (mirrors LeaveRequestResource).
     *
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Display a server-side paginated, filterable list of leave requests.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $tenantId = $request->user()->tenant_id;

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = LeaveRequest::query()
            ->forTenant($tenantId)
            ->with([
                'employee:id,full_name,employee_number,branch_id',
                'employee.branch:id,name',
                'leaveType:id,name',
            ])
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('leave_type_id'), fn ($q, $id) => $q->where('leave_type_id', $id));

        $this->applyBranchScope($query, $request->user());

        $requests = $query
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/cuti/index', [
            'requests' => LeaveRequestResource::collection($requests),
            'filters' => $request->only([
                'search', 'status', 'leave_type_id', 'sort', 'direction', 'per_page',
            ]),
            'leaveTypes' => LeaveType::forTenant($tenantId)
                ->where('status', 'active')
                ->get(['id', 'name', 'default_quota']),
            'employees' => Employee::forTenant($tenantId)
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
            'balances' => LeaveType::forTenant($tenantId)
                ->where('status', 'active')
                ->get()
                ->map(fn (LeaveType $leaveType): array => [
                    'id' => $leaveType->id,
                    'jenis' => $leaveType->name,
                    'total' => $leaveType->default_quota,
                    'sisa' => $leaveType->default_quota,
                    'pct' => '100%',
                ]),
            'overtimeRequests' => OvertimeRequest::forTenant($tenantId)
                ->with('employee:id,full_name,employee_number,branch_id')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (OvertimeRequest $overtime): array => [
                    'id' => $overtime->id,
                    'employee' => $this->shapeEmployee($overtime),
                    'date' => $overtime->date?->format('d M Y'),
                    'hours' => (float) $overtime->hours,
                    'reason' => $overtime->reason,
                    'status' => $overtime->status,
                    'status_label' => $this->statusLabel($overtime->status),
                ]),
            'permissionRequests' => PermissionRequest::forTenant($tenantId)
                ->with('employee:id,full_name,employee_number,branch_id')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (PermissionRequest $permission): array => [
                    'id' => $permission->id,
                    'employee' => $this->shapeEmployee($permission),
                    'date' => $permission->date?->format('d M Y'),
                    'type' => $permission->type,
                    'start_time' => $permission->start_time ? substr((string) $permission->start_time, 0, 5) : null,
                    'end_time' => $permission->end_time ? substr((string) $permission->end_time, 0, 5) : null,
                    'reason' => $permission->reason,
                    'status' => $permission->status,
                    'status_label' => $this->statusLabel($permission->status),
                ]),
            'wfhRequests' => WfhRequest::forTenant($tenantId)
                ->with('employee:id,full_name,employee_number,branch_id')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (WfhRequest $wfh): array => [
                    'id' => $wfh->id,
                    'employee' => $this->shapeEmployee($wfh),
                    'start_date' => $wfh->start_date?->format('d M Y'),
                    'end_date' => $wfh->end_date?->format('d M Y'),
                    'reason' => $wfh->reason,
                    'status' => $wfh->status,
                    'status_label' => $this->statusLabel($wfh->status),
                ]),
        ]);
    }

    /**
     * Shape the eager-loaded employee for a request row, deriving initials and color.
     *
     * @return array{name: string, employee_number: string|null, initials: string, avatar_color: string}|null
     */
    private function shapeEmployee(Model $request): ?array
    {
        $employee = $request->employee;

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
     * Map a status enum value to its Indonesian label.
     */
    private function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /**
     * Persist a new leave request on behalf of an employee under the tenant.
     */
    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $tenantId = $request->user()->tenant_id;
        $data = $request->validated();

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        LeaveRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'leave_type_id' => $data['leave_type_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => (int) $start->diffInDays($end) + 1,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.cuti')
            ->with('success', 'Pengajuan cuti dibuat');
    }

    /**
     * Approve a pending leave request and decrement the matching balance.
     */
    public function approve(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leave);
        $this->authorize('approve', $leave);

        $leave->update(['status' => 'approved']);

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

        return back()->with('success', 'Cuti disetujui');
    }

    /**
     * Reject a pending leave request.
     */
    public function reject(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $leave);
        $this->authorize('reject', $leave);

        $leave->update(['status' => 'rejected']);

        return back()->with('success', 'Cuti ditolak');
    }

    /**
     * Abort with 404 when the leave request does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, LeaveRequest $leave): void
    {
        abort_if((int) $leave->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
