<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\StoreLeaveRequest;
use App\Http\Resources\Avana\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    use AuthorizesRequests;

    /**
     * Sortable columns whitelist for the index DataTable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['start_date', 'created_at'];

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

        $requests = LeaveRequest::query()
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
            ->when($request->query('leave_type_id'), fn ($q, $id) => $q->where('leave_type_id', $id))
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/cuti', [
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
        ]);
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
