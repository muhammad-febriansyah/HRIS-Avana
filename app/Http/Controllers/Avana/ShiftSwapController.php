<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShiftSwapController extends Controller
{
    /**
     * Roles that may always manage shift swaps within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Indonesian labels for the status enum.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    /**
     * Display the list of shift swap requests for the tenant.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $swaps = ShiftSwap::forTenant($tenantId)
            ->with([
                'requester:id,full_name,employee_number',
                'target:id,full_name,employee_number',
                'requesterShift:id,code,name',
                'targetShift:id,code,name',
            ])
            ->latest('id')
            ->get()
            ->map(fn (ShiftSwap $swap): array => $this->shapeSwap($swap));

        return Inertia::render('avana/shift-swap/index', [
            'swaps' => $swaps,
            'employees' => $this->employeeOptions($tenantId),
            'shifts' => $this->shiftOptions($tenantId),
            'kpis' => [
                'pending' => $swaps->where('status', 'pending')->count(),
                'approved' => $swaps->where('status', 'approved')->count(),
                'rejected' => $swaps->where('status', 'rejected')->count(),
                'total' => $swaps->count(),
            ],
        ]);
    }

    /**
     * Persist a new shift swap request under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'requester_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'target_id' => ['required', 'different:requester_id', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'requester_shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'target_shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        ShiftSwap::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => 'pending',
        ]);

        return redirect()->route('avana.shift-swap')
            ->with('success', 'Pengajuan tukar shift dibuat');
    }

    /**
     * Approve a pending shift swap request.
     */
    public function approve(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $swap);

        $swap->update(['status' => 'approved']);

        return back()->with('success', 'Tukar shift disetujui');
    }

    /**
     * Reject a pending shift swap request.
     */
    public function reject(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $swap);

        $swap->update(['status' => 'rejected']);

        return back()->with('success', 'Tukar shift ditolak');
    }

    /**
     * Delete a shift swap request.
     */
    public function destroy(Request $request, ShiftSwap $swap): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $swap);

        $swap->delete();

        return back()->with('success', 'Pengajuan tukar shift dihapus');
    }

    /**
     * Build the row shape consumed by the swap requests table.
     *
     * @return array<string, mixed>
     */
    private function shapeSwap(ShiftSwap $swap): array
    {
        return [
            'id' => $swap->id,
            'requester' => $swap->requester?->full_name,
            'requester_id' => $swap->requester_id,
            'target' => $swap->target?->full_name,
            'target_id' => $swap->target_id,
            'date' => $swap->date?->toDateString(),
            'requester_shift' => $swap->requesterShift?->name,
            'requester_shift_id' => $swap->requester_shift_id,
            'target_shift' => $swap->targetShift?->name,
            'target_shift_id' => $swap->target_shift_id,
            'reason' => $swap->reason,
            'status' => $swap->status,
            'status_label' => self::STATUS_LABELS[$swap->status] ?? $swap->status,
        ];
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Build the tenant's selectable active shift options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shiftOptions(int $tenantId): array
    {
        return Shift::forTenant($tenantId)
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get(['id', 'code', 'name', 'start_time', 'end_time'])
            ->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'code' => $shift->code,
                'name' => $shift->name,
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, ShiftSwap $swap): void
    {
        abort_if((int) $swap->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}
