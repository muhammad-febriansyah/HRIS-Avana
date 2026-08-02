<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\RosterPattern;
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
     * Whether a shift runs past midnight into the next calendar day — an end
     * time at or before the start time is the only way to say so.
     */
    public static function crossesMidnight(Shift $shift): bool
    {
        if ($shift->start_time === null || $shift->end_time === null) {
            return false;
        }

        return substr((string) $shift->end_time, 0, 8) <= substr((string) $shift->start_time, 0, 8);
    }

    /**
     * The roster day a punch belongs to.
     *
     * A night shift is worked on the day it started: someone who clocks in at
     * 22:00 and out at 06:00 has one shift, not two half days. The punch's own
     * calendar date is the answer unless the previous day's shift crosses
     * midnight and is still running, in which case the punch is still part of
     * that night.
     */
    public static function workDateFor(int $tenantId, int $employeeId, CarbonInterface $clockedAt): string
    {
        $previous = $clockedAt->copy()->subDay()->toDateString();
        $shift = self::shiftFor($tenantId, $employeeId, $previous);

        if ($shift !== null && self::crossesMidnight($shift)) {
            $end = $clockedAt->copy()->setTimeFromTimeString((string) $shift->end_time);

            if ($clockedAt->lessThanOrEqualTo($end)) {
                return $previous;
            }
        }

        return $clockedAt->toDateString();
    }

    /**
     * Judge a clock-in against the shift it belongs to: late once the clock
     * time passes the shift start plus its tolerance. An unscheduled day has
     * nothing to be late for.
     *
     * `$workDate` is the roster day the punch belongs to, which is not the
     * punch's own date on a night shift — without it, a 00:30 arrival for a
     * shift that began at 22:00 would be measured against 22:00 *tonight* and
     * come out early rather than two and a half hours late.
     *
     * @return array{status: string, late_minutes: int, shift_id: int|null}
     */
    public static function evaluate(?Shift $shift, CarbonInterface $clockedAt, ?string $workDate = null): array
    {
        if ($shift === null || $shift->start_time === null) {
            return ['status' => 'present', 'late_minutes' => 0, 'shift_id' => $shift?->id];
        }

        // Built on the punch's own clock: comparing a Makassar 08:30 against a
        // Jakarta 08:00 would make a late arrival look half an hour early.
        $start = ($workDate !== null
            ? Carbon::parse($workDate, $clockedAt->getTimezone())
            : $clockedAt->copy())
            ->setTimeFromTimeString((string) $shift->start_time);
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
     * Lay a rotation over a date range for one employee.
     *
     * The cycle is read in order from the start date and repeated until the
     * range runs out, so a 3-3-3-2 pattern started on a Monday keeps rotating
     * regardless of where weeks fall. Days the shift does not run are skipped
     * rather than forced, and counted so the caller can say what it left.
     *
     * @return array{assigned: int, skipped: int}
     */
    public static function applyPattern(
        RosterPattern $pattern,
        int $employeeId,
        string|CarbonInterface $from,
        string|CarbonInterface $until,
    ): array {
        $steps = $pattern->steps;

        if ($steps->isEmpty()) {
            return ['assigned' => 0, 'skipped' => 0];
        }

        // The cycle flattened to one shift per day, which is what makes the
        // repeat a simple modulo rather than a running counter.
        $days = [];
        foreach ($steps as $step) {
            for ($i = 0; $i < max(1, (int) $step->days); $i++) {
                $days[] = $step->shift_id;
            }
        }

        $tenantId = (int) $pattern->tenant_id;
        $shifts = Shift::forTenant($tenantId)->get()->keyBy('id');
        $start = Carbon::parse(self::dateString($from));
        $end = Carbon::parse(self::dateString($until));

        $assigned = 0;
        $skipped = 0;

        for ($date = $start->copy(), $offset = 0; $date->lessThanOrEqualTo($end); $date->addDay(), $offset++) {
            $shiftId = $days[$offset % count($days)];
            $shift = $shiftId !== null ? $shifts->get($shiftId) : null;

            if ($shift !== null && ! self::runsOn($shift, $date)) {
                $skipped++;

                continue;
            }

            self::assign($tenantId, $employeeId, $date, $shiftId);
            $assigned++;
        }

        return ['assigned' => $assigned, 'skipped' => $skipped];
    }

    /**
     * Recompute an attendance record's status and late minutes from the roster
     * — used after a correction changes the clock-in it was judged on.
     *
     * @return array{status: string, late_minutes: int, shift_id: int|null}
     */
    public static function evaluateFor(Employee $employee, CarbonInterface $clockedAt, ?string $workDate = null): array
    {
        $workDate ??= self::workDateFor((int) $employee->tenant_id, (int) $employee->id, $clockedAt);

        return self::evaluate(
            self::shiftFor((int) $employee->tenant_id, (int) $employee->id, $workDate),
            $clockedAt,
            $workDate,
        );
    }

    private static function dateString(string|CarbonInterface $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();
    }
}
