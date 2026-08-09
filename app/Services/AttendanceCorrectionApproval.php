<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Support\AttendanceFines;
use App\Support\Roster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        DB::transaction(function () use ($correction, $approverUserId): void {
            $date = Carbon::parse($correction->date);
            $shift = Roster::shiftFor(
                (int) $correction->tenant_id,
                (int) $correction->employee_id,
                $date,
            );

            $attendance = $correction->attendance ?? Attendance::query()
                ->forTenant($correction->tenant_id)
                ->where('employee_id', $correction->employee_id)
                ->whereDate('date', $date->toDateString())
                ->first() ?? new Attendance([
                    'tenant_id' => $correction->tenant_id,
                    'employee_id' => $correction->employee_id,
                    'date' => $date->toDateString(),
                ]);

            $clockIn = $correction->requested_clock_in !== null
                ? $date->copy()->setTimeFromTimeString($correction->requested_clock_in)
                : $attendance->clock_in_at;
            $clockOut = $correction->requested_clock_out !== null
                ? $date->copy()->setTimeFromTimeString($correction->requested_clock_out)
                : $attendance->clock_out_at;

            if ($clockIn !== null && $clockOut !== null && $clockOut->lessThanOrEqualTo($clockIn)) {
                if ($shift === null || ! Roster::crossesMidnight($shift)) {
                    throw ValidationException::withMessages([
                        'requested_clock_out' => 'Jam pulang harus setelah jam masuk.',
                    ]);
                }

                $clockOut = $clockOut->copy()->addDay();
            }

            $attendance->fill([
                'branch_id' => $attendance->branch_id ?? $correction->employee?->branch_id,
                'shift_id' => $shift?->id,
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'work_minutes' => $clockIn !== null && $clockOut !== null
                    ? max(0, (int) $clockIn->diffInMinutes($clockOut))
                    : 0,
            ]);

            if ($clockIn === null) {
                $attendance->status = 'incomplete';
                $attendance->late_minutes = 0;
            } else {
                $verdict = Roster::evaluate($shift, Carbon::parse($clockIn), $date->toDateString());
                $attendance->status = $clockOut === null ? 'incomplete' : $verdict['status'];
                $attendance->late_minutes = $verdict['late_minutes'];
            }

            $attendance->save();
            AttendanceFines::sync($attendance);

            $correction->update([
                'attendance_id' => $attendance->id,
                'status' => 'approved',
                'approver_id' => $approverUserId,
            ]);
        }, 3);
    }
}
