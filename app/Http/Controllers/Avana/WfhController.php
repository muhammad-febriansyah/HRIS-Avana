<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WfhRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WfhController extends Controller
{
    use AuthorizesRequests;

    /**
     * Persist a new WFH request on behalf of an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', WfhRequest::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);

        WfhRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Pengajuan WFH dibuat');
    }

    /**
     * Approve a pending WFH request.
     */
    public function approve(Request $request, WfhRequest $wfh): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $wfh);
        $this->authorize('approve', $wfh);

        $wfh->update(['status' => 'approved']);

        return back()->with('success', 'WFH disetujui');
    }

    /**
     * Reject a pending WFH request.
     */
    public function reject(Request $request, WfhRequest $wfh): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $wfh);
        $this->authorize('reject', $wfh);

        $wfh->update(['status' => 'rejected']);

        return back()->with('success', 'WFH ditolak');
    }

    /**
     * Abort with 404 when the WFH request does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, WfhRequest $wfh): void
    {
        abort_if((int) $wfh->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
