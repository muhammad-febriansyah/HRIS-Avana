<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use App\Support\OvertimeRules;
use App\Support\OvertimeWindow;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'day_type' => ['nullable', Rule::in(array_keys(OvertimeRules::DAY_TYPES))],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! OvertimeWindow::isPlausible($data['start_time'], $data['end_time'])) {
            throw ValidationException::withMessages([
                'end_time' => sprintf(
                    'Durasi lembur harus antara %s dan %s jam.',
                    OvertimeWindow::MIN_HOURS,
                    OvertimeWindow::MAX_HOURS,
                ),
            ]);
        }

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);
        $hours = OvertimeWindow::hoursBetween($data['start_time'], $data['end_time']);
        $date = Carbon::parse($data['date']);

        // PP 35/2021 caps overtime at 4 hours a day and 18 a week; the ceilings
        // themselves are tenant-configurable in Setup Lembur.
        $violation = OvertimeRules::limitViolation($tenantId, (int) $employee->id, $date, $hours);

        if ($violation !== null) {
            throw ValidationException::withMessages(['end_time' => $violation]);
        }

        $overtime = OvertimeRequest::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'date' => $data['date'],
            'day_type' => OvertimeRules::normaliseDayType($data['day_type'] ?? OvertimeRules::suggestDayType($date)),
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'hours' => $hours,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        // A top approver (director) has no manager above them, so their own
        // request is approved on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            AutoApproval::overtime($overtime);

            return back()->with('success', 'Pengajuan lembur langsung disetujui (approver puncak)');
        }

        // Route through the configured approval workflow when one is active.
        ApprovalEngine::start($overtime, $employee);

        return back()->with('success', 'Pengajuan lembur dibuat');
    }

    /**
     * Approve a pending overtime request.
     */
    public function approve(Request $request, OvertimeRequest $overtime): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $overtime);
        $this->authorize('approve', $overtime);

        if (! ApprovalEngine::decide($overtime, $request->user()->id, 'approve')) {
            $overtime->update(['status' => 'approved']);
        }

        return back()->with('success', $overtime->fresh()?->status === 'approved'
            ? 'Lembur disetujui'
            : 'Persetujuan tercatat, menunggu tahap berikutnya');
    }

    /**
     * Reject a pending overtime request.
     */
    public function reject(Request $request, OvertimeRequest $overtime): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $overtime);
        $this->authorize('reject', $overtime);

        if (! ApprovalEngine::decide($overtime, $request->user()->id, 'reject')) {
            $overtime->update(['status' => 'rejected']);
        }

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
