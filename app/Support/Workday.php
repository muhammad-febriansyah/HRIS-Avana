<?php

namespace App\Support;

use App\Models\AttendancePolicy;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Whether an employee is expected at work on a given date.
 *
 * One answer for every place that needs it: the morning clock-in reminder,
 * the nightly Alpa marker and the live "belum absen" count on the Absensi
 * page. Each of them asking a slightly different question is how someone gets
 * reminded on a holiday, or marked absent on a day they were rostered off.
 *
 * Order matters. Outside the employment window nobody is due. An approved
 * leave answers next — someone on cuti is away, not absent. Then the roster:
 * a row with a shift means due, a row without one is a day off, and that holds
 * on a public holiday too, since a shift company rosters people through them.
 * With no roster row at all the tenant runs a plain office week: Monday to
 * Friday, minus the holidays on its Kalender.
 * Unless the tenant switched on "wajib ada jadwal shift": then no roster
 * row means a day off.
 */
final class Workday
{
    /**
     * Whether one employee is due at work on the date.
     */
    public static function isDue(Employee $employee, CarbonInterface $date): bool
    {
        return self::dueAmong(collect([$employee]), $date)->isNotEmpty();
    }

    /**
     * The employees of one tenant who are due at work on the date.
     *
     * Reads leave, roster and holiday once for the whole set, so a page that
     * asks about a few hundred people costs a few queries, not a few per head.
     *
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, Employee>
     */
    public static function dueAmong(Collection $employees, CarbonInterface $date): Collection
    {
        if ($employees->isEmpty()) {
            return $employees->values();
        }

        $dateString = $date->toDateString();
        $tenantId = (int) $employees->first()->tenant_id;
        $employeeIds = $employees->pluck('id')->all();

        $onLeave = LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dateString)
            ->whereDate('end_date', '>=', $dateString)
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $rostered = ShiftSchedule::forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', $dateString)
            ->get(['employee_id', 'shift_id'])
            ->keyBy(fn (ShiftSchedule $row): int => (int) $row->employee_id);

        // A tenant that only lets rostered days clock in has no office week to
        // fall back on: a day with no roster row is a day off, or its people
        // would be marked Alpa for a day the app refused to let them clock.
        $isOfficeDay = ! AttendancePolicy::resolve($tenantId)->require_shift_schedule
            && ! $date->isWeekend()
            && ! self::isHoliday($tenantId, $dateString);

        return $employees
            ->filter(function (Employee $employee) use ($dateString, $onLeave, $rostered, $isOfficeDay): bool {
                if ($employee->join_date !== null && $employee->join_date->toDateString() > $dateString) {
                    return false;
                }

                if ($employee->resign_date !== null && $employee->resign_date->toDateString() < $dateString) {
                    return false;
                }

                if ($onLeave->has((int) $employee->id)) {
                    return false;
                }

                $schedule = $rostered->get((int) $employee->id);

                if ($schedule !== null) {
                    return $schedule->shift_id !== null;
                }

                return $isOfficeDay;
            })
            ->values();
    }

    /**
     * Whether the tenant's Kalender marks the date as a holiday (Libur).
     */
    public static function isHoliday(int $tenantId, string $date): bool
    {
        return CalendarEvent::forTenant($tenantId)
            ->where('type', 'holiday')
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->where(function ($query) use ($date): void {
                    $query->whereNull('end_date')->whereDate('start_date', '>=', $date);
                })->orWhereDate('end_date', '>=', $date);
            })
            ->exists();
    }
}
