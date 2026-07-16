<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\WfhRequest;
use Illuminate\Support\Carbon;

/**
 * Materialises attendance rows for an approved leave request so every day the
 * leave covers reads as "Cuti" wherever attendance is listed, and so there is a
 * record behind the clock-in guard.
 */
final class LeaveAttendanceMarker
{
    /**
     * Mark every calendar day the leave covers as 'leave'.
     *
     * Leave is counted in calendar days (total_days is start..end inclusive),
     * so weekends inside the range are marked too — matching how the balance is
     * already charged.
     */
    public static function mark(LeaveRequest $leave): void
    {
        foreach (self::dates($leave) as $date) {
            // Matched on tenant+employee+date only: the unique index also spans
            // the nullable shift_id, and NULLs never collide, so it would not
            // stop a duplicate leave row on its own.
            $existing = Attendance::query()
                ->where('tenant_id', $leave->tenant_id)
                ->where('employee_id', $leave->employee_id)
                ->whereDate('date', $date)
                ->first();

            // A real clock-in is a fact — never bury it under a leave marker.
            if ($existing?->clock_in_at !== null) {
                continue;
            }

            ($existing ?? new Attendance)->fill([
                'tenant_id' => $leave->tenant_id,
                'employee_id' => $leave->employee_id,
                'branch_id' => $leave->branch_id,
                'date' => $date,
                'status' => 'leave',
                'late_minutes' => 0,
                'work_minutes' => 0,
                'location_status' => null,
            ])->save();
        }
    }

    /**
     * Whether an approved leave covers this date for this employee.
     */
    public static function covers(int $tenantId, int $employeeId, string $date): bool
    {
        return LeaveRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Whether an approved WFH request covers this date for this employee —
     * the licence for clocking in with work_mode 'home'.
     */
    public static function wfhCovers(int $tenantId, int $employeeId, string $date): bool
    {
        return WfhRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Every calendar date the leave covers, inclusive of both ends.
     *
     * @return list<string>
     */
    private static function dates(LeaveRequest $leave): array
    {
        $start = Carbon::parse($leave->start_date)->startOfDay();
        $end = Carbon::parse($leave->end_date)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
