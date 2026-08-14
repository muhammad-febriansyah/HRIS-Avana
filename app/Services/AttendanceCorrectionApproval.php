<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Support\AttendanceCorrectionTimes;
use App\Support\Roster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            $lockedCorrection = AttendanceCorrection::query()
                ->whereKey($correction->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCorrection->status === 'approved') {
                return;
            }

            abort_unless($lockedCorrection->status === 'pending', 422, 'Pengajuan ini sudah diproses.');

            $employee = Employee::forTenant((int) $lockedCorrection->tenant_id)
                ->whereKey($lockedCorrection->employee_id)
                ->lockForUpdate()
                ->firstOrFail();
            $date = Carbon::parse($lockedCorrection->date);
            $dateString = $date->toDateString();

            $attendance = $lockedCorrection->attendance_id !== null
                ? Attendance::forTenant($lockedCorrection->tenant_id)
                    ->where('employee_id', $lockedCorrection->employee_id)
                    ->whereKey($lockedCorrection->attendance_id)
                    ->lockForUpdate()
                    ->first()
                : null;

            $attendance ??= Attendance::forTenant($lockedCorrection->tenant_id)
                ->where('employee_id', $lockedCorrection->employee_id)
                ->whereDate('date', $dateString)
                ->lockForUpdate()
                ->first();
            $attendance ??= new Attendance([
                'tenant_id' => $lockedCorrection->tenant_id,
                'employee_id' => $lockedCorrection->employee_id,
                'date' => $dateString,
            ]);

            $shift = Roster::shiftFor(
                (int) $lockedCorrection->tenant_id,
                (int) $lockedCorrection->employee_id,
                $dateString,
            );

            if ($lockedCorrection->requested_clock_in !== null) {
                $attendance->clock_in_at = AttendanceCorrectionTimes::onWorkDate(
                    $date,
                    $lockedCorrection->requested_clock_in,
                    $shift,
                );
            }

            if ($lockedCorrection->requested_clock_out !== null) {
                $attendance->clock_out_at = AttendanceCorrectionTimes::onWorkDate(
                    $date,
                    $lockedCorrection->requested_clock_out,
                    $shift,
                );
            }

            $attendance->branch_id ??= $employee->branch_id;
            $attendance->shift_id ??= $shift?->id;

            if ($attendance->clock_in_at !== null) {
                $clockedAt = Carbon::parse($attendance->clock_in_at);
                $verdict = Roster::evaluate($shift, $clockedAt, $dateString);

                $attendance->status = $verdict['status'];
                $attendance->late_minutes = $verdict['late_minutes'];
                $attendance->shift_id = $verdict['shift_id'] ?? $attendance->shift_id;
            } else {
                $attendance->status = 'need_correction';
                $attendance->late_minutes = 0;
            }

            if ($attendance->clock_in_at !== null && $attendance->clock_out_at !== null) {
                $clockIn = Carbon::parse($attendance->clock_in_at);
                $clockOut = Carbon::parse($attendance->clock_out_at);
                $attendance->work_minutes = $clockOut->greaterThanOrEqualTo($clockIn)
                    ? (int) $clockIn->diffInMinutes($clockOut)
                    : 0;
            } else {
                $attendance->work_minutes = 0;
            }

            $attendance->save();

            $lockedCorrection->update([
                'attendance_id' => $attendance->id,
                'status' => 'approved',
                'approver_id' => $approverUserId,
            ]);
        }, 3);
    }
}
