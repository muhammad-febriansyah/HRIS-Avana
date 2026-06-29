<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OvertimeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Persist a new overtime request on behalf of an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', OvertimeRequest::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);

        OvertimeRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'date' => $data['date'],
            'hours' => $data['hours'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan lembur dibuat');
    }

    /**
     * Approve a pending overtime request.
     */
    public function approve(Request $request, OvertimeRequest $overtime): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $overtime);
        $this->authorize('approve', $overtime);

        $overtime->update(['status' => 'approved']);

        return back()->with('success', 'Lembur disetujui');
    }

    /**
     * Reject a pending overtime request.
     */
    public function reject(Request $request, OvertimeRequest $overtime): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $overtime);
        $this->authorize('reject', $overtime);

        $overtime->update(['status' => 'rejected']);

        return back()->with('success', 'Lembur ditolak');
    }

    /**
     * Abort with 404 when the overtime request does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, OvertimeRequest $overtime): void
    {
        abort_if((int) $overtime->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
