<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Shift;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class AttendanceCorrectionTimes
{
    public static function rangeIsValid(
        Employee $employee,
        string|CarbonInterface $date,
        ?string $clockIn,
        ?string $clockOut,
    ): bool {
        if ($clockIn === null || $clockOut === null || $clockOut > $clockIn) {
            return true;
        }

        $shift = Roster::shiftFor(
            (int) $employee->tenant_id,
            (int) $employee->getKey(),
            $date,
        );

        return $clockOut < $clockIn
            && $shift !== null
            && Roster::crossesMidnight($shift);
    }

    public static function onWorkDate(string|CarbonInterface $date, string $time, ?Shift $shift): Carbon
    {
        $timestamp = Carbon::parse($date)->startOfDay()->setTimeFromTimeString($time);

        if ($shift !== null
            && Roster::crossesMidnight($shift)
            && substr($time, 0, 5) < substr((string) $shift->start_time, 0, 5)) {
            $timestamp->addDay();
        }

        return $timestamp;
    }
}
