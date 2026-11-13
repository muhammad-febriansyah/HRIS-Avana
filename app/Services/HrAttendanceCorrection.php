<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\User;
use App\Support\Roster;
use Illuminate\Support\Facades\DB;

/**
 * HR correcting a day's attendance directly from the Absensi page.
 *
 * Every correction is written as an approved AttendanceCorrection signed by
 * the HR user, so the employee's own "Koreksi Absensi" history shows what was
 * changed and why, and the activity log keeps the before/after. A day set to
 * Hadir goes through the same finalize step as an employee's approved request
 * — lateness is judged against the shift rostered for that date, not typed in.
 */
final class HrAttendanceCorrection
{
    /** Hadir: clock times given, status worked out from the shift. */
    public const STATUS_PRESENT = 'hadir';

    /** Cuti / Izin: away with leave, not docked. */
    public const STATUS_LEAVE = 'leave';

    /** Alpa: absent without leave, docked in payroll. */
    public const STATUS_ABSENT = 'absent';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PRESENT, self::STATUS_LEAVE, self::STATUS_ABSENT];

    /**
     * Apply the correction and return the attendance row it produced.
     */
    public static function apply(
        Employee $employee,
        string $date,
        string $status,
        ?string $clockIn,
        ?string $clockOut,
        string $reason,
        User $hr,
    ): Attendance {
        return DB::transaction(function () use ($employee, $date, $status, $clockIn, $clockOut, $reason, $hr): Attendance {
            $tenantId = (int) $employee->tenant_id;

            $before = Attendance::forTenant($tenantId)
                ->where('employee_id', $employee->id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            $snapshot = $before === null ? null : [
                'status' => $before->status,
                'clock_in' => $before->clock_in_at?->format('H:i'),
                'clock_out' => $before->clock_out_at?->format('H:i'),
            ];

            $correction = AttendanceCorrection::create([
                'tenant_id' => $tenantId,
                'attendance_id' => $before?->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'correction_type' => 'hr_'.$status,
                'requested_clock_in' => $status === self::STATUS_PRESENT ? $clockIn : null,
                'requested_clock_out' => $status === self::STATUS_PRESENT ? $clockOut : null,
                'reason' => $reason,
                'status' => 'pending',
            ]);

            if ($status === self::STATUS_PRESENT) {
                AttendanceCorrectionApproval::finalize($correction, (int) $hr->id);

                $attendance = Attendance::findOrFail($correction->fresh()->attendance_id);
            } else {
                $attendance = $before ?? new Attendance([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'date' => $date,
                ]);

                $attendance->branch_id ??= $employee->branch_id;
                $attendance->shift_id ??= Roster::shiftFor($tenantId, (int) $employee->id, $date)?->id;
                // The punches, if any, stay as evidence; the verdict is HR's.
                $attendance->status = $status;
                $attendance->late_minutes = 0;
                $attendance->save();

                $correction->update([
                    'attendance_id' => $attendance->id,
                    'status' => 'approved',
                    'approver_id' => $hr->id,
                ]);
            }

            ActivityLogger::log(
                'attendance.hr_correction',
                sprintf('Koreksi absensi %s tanggal %s oleh HR', $employee->full_name, $date),
                [
                    'employee_id' => $employee->id,
                    'date' => $date,
                    'before' => $snapshot,
                    'after' => [
                        'status' => $attendance->status,
                        'clock_in' => $attendance->clock_in_at?->format('H:i'),
                        'clock_out' => $attendance->clock_out_at?->format('H:i'),
                    ],
                    'reason' => $reason,
                    'attendance_correction_id' => $correction->id,
                ],
                $hr,
                $attendance,
            );

            return $attendance;
        });
    }
}
