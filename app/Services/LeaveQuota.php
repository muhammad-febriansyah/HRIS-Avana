<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;

/**
 * The single place that answers "may this employee take these days off?".
 *
 * Two ceilings apply. The parent quota is the real balance: a sub-type never
 * has its own, it draws the parent's down. On top of that a sub-type may carry
 * `sub_limit`, capping how much of that shared pool it is allowed to consume in
 * a year — so "Cuti Bersama max 3" cannot eat all 12 annual-leave days.
 *
 * Used by the admin form request, the ESS page, and the mobile API so the three
 * entry points cannot drift apart.
 */
class LeaveQuota
{
    /**
     * Days still available from the quota-owning type's balance for the year.
     * Falls back to the type's configured quota when no balance row exists yet.
     */
    public static function remaining(int $employeeId, LeaveType $type, int $year): float
    {
        $balance = LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $type->quotaOwnerId())
            ->where('year', $year)
            ->first();

        return $balance !== null
            ? (float) $balance->remaining
            : (float) $type->effectiveQuota();
    }

    /**
     * Days still available under a sub-type's own cap, or null when the type is
     * a root or carries no cap.
     */
    public static function subRemaining(int $employeeId, LeaveType $type, int $year): ?float
    {
        if (! $type->isSub() || $type->sub_limit === null) {
            return null;
        }

        $taken = (float) LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $type->getKey())
            ->whereIn('status', ['pending', 'approved'])
            ->whereYear('start_date', $year)
            ->sum('total_days');

        return max(0.0, (float) $type->sub_limit - $taken);
    }

    /**
     * Validate a requested duration against both ceilings.
     *
     * @return string|null an Indonesian error message, or null when allowed
     */
    public static function check(int $employeeId, LeaveType $type, float $totalDays, int $year): ?string
    {
        // A branched root only groups its sub-types; the days have to be booked
        // against one of them or the sub-caps mean nothing.
        if (! $type->isSub() && ! $type->isSelectable()) {
            return sprintf('Pilih sub-jenis dari %s.', $type->name);
        }

        $subRemaining = self::subRemaining($employeeId, $type, $year);

        // The sub-cap holds even when the parent allows a negative balance —
        // it is a policy limit on the sub-type, not a balance.
        if ($subRemaining !== null && $totalDays > $subRemaining) {
            return sprintf(
                'Jatah %s tinggal %s hari dari batas %d hari per tahun.',
                $type->name,
                self::formatDays($subRemaining),
                (int) $type->sub_limit,
            );
        }

        if ($type->effectiveAllowNegative()) {
            return null;
        }

        $remaining = self::remaining($employeeId, $type, $year);

        if ($totalDays > $remaining) {
            $owner = $type->quotaOwner();

            return $type->isSub()
                ? sprintf(
                    'Saldo %s tidak mencukupi (sisa %s hari).',
                    $owner->name,
                    self::formatDays($remaining),
                )
                : sprintf('Saldo cuti tidak mencukupi (sisa %s hari).', self::formatDays($remaining));
        }

        return null;
    }

    /**
     * Resolve the year a request draws from: the year its first day falls in.
     */
    public static function yearOf(string $startDate): int
    {
        return Carbon::parse($startDate)->year;
    }

    /**
     * Format a day count without trailing decimal zeros.
     */
    private static function formatDays(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
