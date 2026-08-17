<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WfhRequest;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use App\Support\FeatureGate;
use App\Support\RequestDateClash;
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
        FeatureGate::ensure($request->user(), 'wfh');

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

        $clash = RequestDateClash::check(
            (int) $tenantId,
            (int) $employee->id,
            $data['start_date'],
            $data['end_date'],
        );

        if ($clash !== null) {
            return back()->withErrors(['start_date' => $clash]);
        }

        $wfh = WfhRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            // Without an approver the request lands in nobody's queue but HR's.
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        // A top approver (director) has no manager above them, so their own
        // request is approved on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            AutoApproval::wfh($wfh);

            return back()->with('success', 'Pengajuan WFH langsung disetujui (approver puncak)');
        }

        // Route through the configured approval workflow when one is active.
        ApprovalEngine::start($wfh, $employee);

        return back()->with('success', 'Pengajuan WFH dibuat');
    }

    /**
     * Approve a pending WFH request.
     */
    public function approve(Request $request, WfhRequest $wfh): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $wfh);
        $this->authorize('approve', $wfh);

        if (! ApprovalEngine::decide($wfh, $request->user()->id, 'approve')) {
            AutoApproval::wfh($wfh);
        }

        return back()->with('success', $wfh->fresh()?->status === 'approved'
            ? 'WFH disetujui'
            : 'Persetujuan tercatat, menunggu tahap berikutnya');
    }

    /**
     * Reject a pending WFH request.
     */
    public function reject(Request $request, WfhRequest $wfh): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $wfh);
        $this->authorize('reject', $wfh);

        if (! ApprovalEngine::decide($wfh, $request->user()->id, 'reject')) {
            $wfh->update(['status' => 'rejected']);
        }

        return back()->with('success', 'WFH ditolak');
    }

    /**
     * Abort with 404 when the WFH request does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, WfhRequest $wfh): void
    {
        FeatureGate::ensure($request->user(), 'wfh');

        abort_if((int) $wfh->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}
