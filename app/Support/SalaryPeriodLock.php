<?php

namespace App\Support;

use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;

/**
 * Where a salary change is allowed to start.
 *
 * A finalized payroll is a snapshot: its payslips, tax and BPJS filings are
 * already out. Backdating a salary into a period that has been locked would
 * make the stored figures disagree with the salary they were computed from,
 * so the change is refused and the difference is paid through Rapel instead.
 */
final class SalaryPeriodLock
{
    /**
     * The last day already covered by a finalized payroll period, or null when
     * nothing has been finalized yet.
     */
    public static function lockedThrough(int $tenantId): ?Carbon
    {
        $lastDay = PayrollPeriod::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'locked')
            ->max('end_date');

        return $lastDay === null ? null : Carbon::parse($lastDay)->startOfDay();
    }

    /**
     * Whether a salary starting on this date would fall inside a finalized
     * period.
     */
    public static function blocks(int $tenantId, Carbon $from): bool
    {
        $lockedThrough = self::lockedThrough($tenantId);

        return $lockedThrough !== null && $from->startOfDay()->lessThanOrEqualTo($lockedThrough);
    }

    /**
     * The refusal shown to HR, naming the date they can use instead.
     */
    public static function message(Carbon $lockedThrough): string
    {
        return 'Payroll sampai '.$lockedThrough->translatedFormat('d F Y')
            .' sudah final. Tanggal berlaku paling awal '.$lockedThrough->copy()->addDay()->translatedFormat('d F Y')
            .'. Untuk kekurangan pembayaran periode yang sudah final, gunakan menu Rapel.';
    }

    /**
     * The guard as one call: null when the date is allowed, otherwise the
     * message explaining why it is not.
     */
    public static function refusal(int $tenantId, Carbon $from): ?string
    {
        $lockedThrough = self::lockedThrough($tenantId);

        if ($lockedThrough === null || $from->copy()->startOfDay()->greaterThan($lockedThrough)) {
            return null;
        }

        return self::message($lockedThrough);
    }
}
