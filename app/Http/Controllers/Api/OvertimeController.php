<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Services\ApprovalEngine;
use App\Services\AutoApproval;
use App\Support\OvertimeWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Employee self-service overtime requests. */
class OvertimeController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = OvertimeRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('date')
            ->get(['id', 'date', 'start_time', 'end_time', 'hours', 'reason', 'status'])
            ->map(fn (OvertimeRequest $o): array => [
                'id' => $o->id,
                'date' => $o->date instanceof Carbon ? $o->date->toDateString() : $o->date,
                'hours' => (float) $o->hours,
                'start_time' => $o->start_time !== null ? substr($o->start_time, 0, 5) : null,
                'end_time' => $o->end_time !== null ? substr($o->end_time, 0, 5) : null,
                'time_range' => OvertimeWindow::label($o->start_time, $o->end_time),
                'reason' => $o->reason,
                'status' => $o->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        // Hours are derived, never taken from the client: the range is what the
        // employee filed and what an approver checks, so payroll must agree
        // with it by construction.
        if (! OvertimeWindow::isPlausible($data['start_time'], $data['end_time'])) {
            throw ValidationException::withMessages([
                'end_time' => sprintf(
                    'Durasi lembur harus antara %s dan %s jam.',
                    OvertimeWindow::MIN_HOURS,
                    OvertimeWindow::MAX_HOURS,
                ),
            ]);
        }

        $overtime = OvertimeRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'hours' => OvertimeWindow::hoursBetween($data['start_time'], $data['end_time']),
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        // A top approver (director) has no manager above them, so their own
        // request is approved on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            AutoApproval::overtime($overtime);

            return response()->json([
                'message' => 'Pengajuan lembur langsung disetujui (approver puncak)',
                'data' => ['id' => $overtime->id, 'status' => 'approved'],
            ], 201);
        }

        // Route through the configured approval workflow when one is active.
        ApprovalEngine::start($overtime, $employee);

        return response()->json(['message' => 'Pengajuan lembur terkirim', 'data' => ['id' => $overtime->id]], 201);
    }
}
