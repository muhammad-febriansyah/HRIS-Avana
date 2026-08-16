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

        if ($lockedThrough !== null && $from->copy()->startOfDay()->lessThanOrEqualTo($lockedThrough)) {
            return self::message($lockedThrough);
        }

        $splitPeriod = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<', $from->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->orderBy('start_date')
            ->first(['name', 'start_date', 'end_date']);

        if ($splitPeriod === null) {
            return null;
        }

        return 'Tanggal berlaku berada di tengah periode '.$splitPeriod->name.' ('
            .$splitPeriod->start_date->translatedFormat('d F Y').'–'.$splitPeriod->end_date->translatedFormat('d F Y')
            .'). Gunakan tanggal awal periode agar perhitungan gaji tidak mencampur dua versi dalam satu periode.';
    }

    /**
     * Suggest a safe date for the salary forms. Existing period starts are
     * preferred because payroll calculates one salary version per period.
     */
    /**
     * Whether a payroll period covering this date has already been finalised.
     *
     * Different question from refusal(): a salary change must start on a period
     * boundary, but a correction, a rapel or an incentive legitimately sits in
     * the middle of a period. What none of them may do is change a period that
     * has already been paid.
     */
    public static function paidPeriodFor(int $tenantId, Carbon $on): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'locked')
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', $on->toDateString())
            ->whereDate('end_date', '>=', $on->toDateString())
            ->first();
    }

    public static function suggestedDate(int $tenantId): Carbon
    {
        $today = Carbon::parse(today()->toDateString())->startOfDay();
        $containingPeriod = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->whereDate('start_date', '<=', $today->toDateString())
            ->whereDate('end_date', '>=', $today->toDateString())
            ->orderByDesc('start_date')
            ->first(['start_date', 'end_date']);

        if ($containingPeriod === null || $today->isSameDay($containingPeriod->start_date)) {
            return Carbon::parse($today->toDateString())->startOfDay();
        }

        $nextPeriodStart = PayrollPeriod::forTenant($tenantId)
            ->where('code', 'not like', 'THR-%')
            ->whereDate('start_date', '>', $today->toDateString())
            ->orderBy('start_date')
            ->value('start_date');

        return $nextPeriodStart === null
            ? Carbon::parse($containingPeriod->end_date->toDateString())->addDay()->startOfDay()
            : Carbon::parse($nextPeriodStart)->startOfDay();
    }
}
