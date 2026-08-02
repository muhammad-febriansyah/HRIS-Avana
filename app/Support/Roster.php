<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The roster as the rest of the system should read it.
 *
 * Attendance judges lateness against the shift a person was actually rostered
 * onto for that date, and more than one place needs that answer — a phone
 * clocking in, an approved attendance correction, a shift swap taking effect.
 * Each of them had its own idea of it, or no idea at all, so the rules live
 * here once.
 */
final class Roster
{
    /**
     * The roster row for an employee on a date, if any.
     *
     * A row with no shift is a deliberate day off, which is not the same thing
     * as never having been rostered — the caller can tell the two apart by
     * whether this returns null.
     */
    public static function scheduleFor(int $tenantId, int $employeeId, string|CarbonInterface $date): ?ShiftSchedule
    {
        return ShiftSchedule::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', self::dateString($date))
            ->with('shift')
            ->first();
    }

    /**
     * The shift an employee is rostered onto for a date, or null when the day
     * is unscheduled or marked off.
     */
    public static function shiftFor(int $tenantId, int $employeeId, string|CarbonInterface $date): ?Shift
    {
        return self::scheduleFor($tenantId, $employeeId, $date)?->shift;
    }

    /**
     * Judge a clock-in against the shift it belongs to: late once the clock
     * time passes the shift start plus its tolerance. An unscheduled day has
     * nothing to be late for.
     *
     * @return array{status: string, late_minutes: int, shift_id: int|null}
     */
    public static function evaluate(?Shift $shift, CarbonInterface $clockedAt): array
    {
        if ($shift === null || $shift->start_time === null) {
            return ['status' => 'present', 'late_minutes' => 0, 'shift_id' => $shift?->id];
        }

        $start = $clockedAt->copy()->setTimeFromTimeString((string) $shift->start_time);
        $allowed = $start->copy()->addMinutes((int) $shift->late_tolerance_minutes);

        if ($clockedAt->lessThanOrEqualTo($allowed)) {
            return ['status' => 'present', 'late_minutes' => 0, 'shift_id' => $shift->id];
        }

        return [
            'status' => 'late',
            'late_minutes' => (int) $start->diffInMinutes($clockedAt),
            'shift_id' => $shift->id,
        ];
    }

    /**
     * Whether a shift runs on a given date.
     *
     * `work_days` holds Carbon day numbers (0 = Sunday). A shift that names no
     * days runs every day — that is what every row written before the column
     * was enforced looks like, and treating it as "never" would empty the
     * roster overnight.
     */
    public static function runsOn(Shift $shift, string|CarbonInterface $date): bool
    {
        $days = $shift->work_days;

        if (! is_array($days) || $days === []) {
            return true;
        }

        $day = Carbon::parse(self::dateString($date))->dayOfWeek;

        return in_array($day, array_map('intval', $days), true);
    }

    /**
     * Indonesian day names for the numbers `work_days` stores, so a rejection
     * can say which days the shift actually runs.
     *
     * @return list<string>
     */
    public static function dayNames(Shift $shift): array
    {
        $labels = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $days = is_array($shift->work_days) ? $shift->work_days : [];

        return array_values(array_filter(array_map(
            fn ($day): ?string => $labels[(int) $day] ?? null,
            $days,
        )));
    }

    /**
     * Put an employee on a shift for a date, or mark the day off with a null
     * shift. One row per employee per date, always.
     */
    public static function assign(int $tenantId, int $employeeId, string|CarbonInterface $date, ?int $shiftId): ShiftSchedule
    {
        $on = self::dateString($date);

        $schedule = ShiftSchedule::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', $on)
            ->first();

        if ($schedule !== null) {
            $schedule->update(['shift_id' => $shiftId]);

            return $schedule;
        }

        return ShiftSchedule::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'date' => $on,
            'shift_id' => $shiftId,
        ]);
    }

    /**
     * Recompute an attendance record's status and late minutes from the roster
     * — used after a correction changes the clock-in it was judged on.
     *
     * @return array{status: string, late_minutes: int, shift_id: int|null}
     */
    public static function evaluateFor(Employee $employee, CarbonInterface $clockedAt): array
    {
        return self::evaluate(
            self::shiftFor((int) $employee->tenant_id, (int) $employee->id, $clockedAt),
            $clockedAt,
        );
    }

    private static function dateString(string|CarbonInterface $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }
}
