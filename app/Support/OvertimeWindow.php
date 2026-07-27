<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The stretch of time an overtime request covers.
 *
 * Overtime is filed as "from 18:00 to 20:00" and payroll reads hours, so the
 * two must never disagree — the duration is derived here rather than typed by
 * whoever files the request.
 */
final class OvertimeWindow
{
    /**
     * Longest single stretch anyone may file, in hours. A range that computes
     * to more than this is a typo (or a date confusion), not a shift.
     */
    public const MAX_HOURS = 12.0;

    /**
     * Shortest stretch worth filing.
     */
    public const MIN_HOURS = 0.5;

    /**
     * Hours between two `HH:MM` times, rounded to two decimals.
     *
     * An end at or before the start means the work ran past midnight — the
     * common case for evening overtime — so it lands on the next day.
     */
    public static function hoursBetween(string $start, string $end): float
    {
        $from = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $to = Carbon::createFromFormat('H:i', substr($end, 0, 5));

        if ($to->lessThanOrEqualTo($from)) {
            $to = $to->addDay();
        }

        return round($from->floatDiffInHours($to), 2);
    }

    /**
     * Whether a range is one a person could plausibly have worked.
     */
    public static function isPlausible(string $start, string $end): bool
    {
        $hours = self::hoursBetween($start, $end);

        return $hours >= self::MIN_HOURS && $hours <= self::MAX_HOURS;
    }

    /**
     * "18:00 – 20:00" for display, or null when a request predates the range.
     */
    public static function label(?string $start, ?string $end): ?string
    {
        if ($start === null || $end === null) {
            return null;
        }

        return substr($start, 0, 5).' – '.substr($end, 0, 5);
    }
}
