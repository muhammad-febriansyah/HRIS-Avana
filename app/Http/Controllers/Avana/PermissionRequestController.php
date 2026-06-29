<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PermissionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * Persist a new permission (izin) request on behalf of an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PermissionRequest::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'max:50'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);

        PermissionRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan izin dibuat');
    }

    /**
     * Approve a pending permission (izin) request.
     */
    public function approve(Request $request, PermissionRequest $permissionRequest): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $permissionRequest);
        $this->authorize('approve', $permissionRequest);

        $permissionRequest->update(['status' => 'approved']);

        return back()->with('success', 'Izin disetujui');
    }

    /**
     * Reject a pending permission (izin) request.
     */
    public function reject(Request $request, PermissionRequest $permissionRequest): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $permissionRequest);
        $this->authorize('reject', $permissionRequest);

        $permissionRequest->update(['status' => 'rejected']);

        return back()->with('success', 'Izin ditolak');
    }

    /**
     * Abort with 404 when the permission request does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, PermissionRequest $permissionRequest): void
    {
        abort_if((int) $permissionRequest->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
