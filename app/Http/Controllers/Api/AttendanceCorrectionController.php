<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee self-service attendance corrections: a request to set or fix a
 * clock-in / clock-out on a past day (forgot to clock, wrong time). Approval is
 * routed to the employee's manager via MSS; only an approval writes to the
 * attendance record, so past-day changes always leave an auditable trail.
 */
class AttendanceCorrectionController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = AttendanceCorrection::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('date')
            ->get()
            ->map(fn (AttendanceCorrection $c): array => [
                'id' => $c->id,
                'date' => $c->date instanceof Carbon ? $c->date->toDateString() : $c->date,
                'requested_clock_in' => $this->shortTime($c->requested_clock_in),
                'requested_clock_out' => $this->shortTime($c->requested_clock_out),
                'reason' => $c->reason,
                'status' => $c->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'requested_clock_in' => ['nullable', 'required_without:requested_clock_out', 'date_format:H:i'],
            'requested_clock_out' => ['nullable', 'required_without:requested_clock_in', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if (isset($data['requested_clock_in'], $data['requested_clock_out'])
            && $data['requested_clock_out'] <= $data['requested_clock_in']) {
            return response()->json([
                'message' => 'Jam pulang harus setelah jam masuk.',
            ], 422);
        }

        // Link the existing attendance row for that day when one exists so the
        // approval can sync it in place; a missing row is created on approval.
        $attendance = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $data['date'])
            ->first();

        $correction = AttendanceCorrection::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'attendance_id' => $attendance?->id,
            'date' => $data['date'],
            'correction_type' => 'manual',
            'requested_clock_in' => $data['requested_clock_in'] ?? null,
            'requested_clock_out' => $data['requested_clock_out'] ?? null,
            'reason' => $data['reason'],
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Pengajuan koreksi absen terkirim', 'data' => ['id' => $correction->id]], 201);
    }

    private function shortTime(?string $time): ?string
    {
        return ($time === null || $time === '') ? null : substr($time, 0, 5);
    }
}
