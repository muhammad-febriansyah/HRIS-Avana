<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Illuminate\Support\Carbon;

class AttendanceCorrectionApproval
{
    /**
     * Approve an attendance correction and write it to the attendance record:
     * set the requested clock-in / clock-out on that day, recompute worked
     * minutes when both are present, and link the record back to the request.
     * The attendance row is created when the employee had none for that day
     * (the "forgot to clock in entirely" case). Shared by the manual approve
     * action and the workflow engine's finalize step.
     */
    public static function finalize(AttendanceCorrection $correction, ?int $approverUserId): void
    {
        $correction->update([
            'status' => 'approved',
            'approver_id' => $approverUserId,
        ]);

        $date = Carbon::parse($correction->date);

        $attendance = $correction->attendance ?? Attendance::firstOrNew([
            'tenant_id' => $correction->tenant_id,
            'employee_id' => $correction->employee_id,
            'date' => $date->toDateString(),
        ]);

        $clockIn = $correction->requested_clock_in !== null
            ? $date->copy()->setTimeFromTimeString($correction->requested_clock_in)
            : null;
        $clockOut = $correction->requested_clock_out !== null
            ? $date->copy()->setTimeFromTimeString($correction->requested_clock_out)
            : null;

        if ($clockIn !== null) {
            $attendance->clock_in_at = $clockIn;
        }
        if ($clockOut !== null) {
            $attendance->clock_out_at = $clockOut;
        }
        if ($attendance->branch_id === null) {
            $attendance->branch_id = $correction->employee?->branch_id;
        }
        $attendance->status = 'present';

        if ($clockIn !== null && $clockOut !== null) {
            $attendance->work_minutes = max(0, (int) $clockIn->diffInMinutes($clockOut));
        }

        $attendance->save();

        $correction->update(['attendance_id' => $attendance->id]);
    }
}
