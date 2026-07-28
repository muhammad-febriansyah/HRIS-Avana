<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * When a tenant's subscription runs out, and how loudly to say so.
 *
 * One place answers it for everything that acts on the term: the banner shared
 * into every tenant page, the milestone reminders sent by `avana:remind-billing`,
 * and the `EnsureSubscriptionActive` gate that closes the product once `level`
 * reaches `expired`.
 */
final class SubscriptionStatus
{
    /**
     * How many days out the warning starts.
     */
    public const WARNING_DAYS = 30;

    /**
     * Below this many days left the warning turns urgent (red).
     */
    public const CRITICAL_DAYS = 7;

    /**
     * Days-left values that earn their own notification, newest first. A tenant
     * gets at most one notification per milestone per period, so a daily run is
     * safe to repeat.
     *
     * @var array<int, int>
     */
    public const MILESTONE_DAYS = [30, 14, 7, 3, 1, 0];

    /**
     * The tenant's subscription standing, or null when no end date is known
     * (an open-ended tenant never expires, so there is nothing to warn about).
     *
     * `level` is `expired` past the end date, `critical` inside
     * {@see self::CRITICAL_DAYS}, `warning` inside {@see self::WARNING_DAYS},
     * and `ok` beyond that.
     *
     * @return array{end_date: string, end_date_label: string, days_left: int, level: string, package: string|null}|null
     */
    public static function forTenant(Tenant $tenant): ?array
    {
        $cache = app(SubscriptionStatusCache::class);

        if ($cache->has($tenant->id)) {
            return $cache->get($tenant->id);
        }

        $status = self::resolve($tenant);
        $cache->put($tenant->id, $status);

        return $status;
    }

    /**
     * Whether the term has run out. The lock gate asks this on every request, so
     * the answer is memoised per tenant for the life of the request.
     */
    public static function isExpired(Tenant $tenant): bool
    {
        return (self::forTenant($tenant)['level'] ?? null) === 'expired';
    }

    /**
     * Drop the memo. Needed after the end date moves (a renewal applied in this
     * request must leave the tenant unlocked, not still expired).
     */
    public static function forget(?int $tenantId = null): void
    {
        app(SubscriptionStatusCache::class)->forget($tenantId);
    }

    /**
     * @return array{end_date: string, end_date_label: string, days_left: int, level: string, package: string|null}|null
     */
    private static function resolve(Tenant $tenant): ?array
    {
        $subscription = Subscription::query()
            ->forTenant($tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->whereNotNull('end_date')
            ->with('package:id,name')
            ->orderByDesc('end_date')
            ->first();

        $endDate = $subscription?->end_date ?? $tenant->end_date;

        if ($endDate === null) {
            return null;
        }

        $endDate = Carbon::parse($endDate)->startOfDay();
        $daysLeft = (int) Carbon::today()->diffInDays($endDate, false);

        $tenant->loadMissing('package:id,name');

        return [
            'end_date' => $endDate->toDateString(),
            'end_date_label' => $endDate->locale('id')->translatedFormat('d F Y'),
            'days_left' => $daysLeft,
            'level' => self::level($daysLeft),
            'package' => $subscription?->package?->name ?? $tenant->package?->name,
        ];
    }

    /**
     * The milestone tag for a days-left value, or null when today is not a
     * milestone. Carries the end date so extending the subscription starts a
     * fresh set of reminders instead of being silenced by the old ones.
     */
    public static function milestone(string $endDate, int $daysLeft): ?string
    {
        if ($daysLeft < 0) {
            return $endDate.':expired';
        }

        return in_array($daysLeft, self::MILESTONE_DAYS, true)
            ? $endDate.':'.$daysLeft
            : null;
    }

    /**
     * Human phrasing for how long is left, e.g. "5 hari lagi" or "hari ini".
     */
    public static function countdownLabel(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'sudah berakhir',
            $daysLeft === 0 => 'berakhir hari ini',
            $daysLeft === 1 => 'berakhir besok',
            default => 'berakhir '.$daysLeft.' hari lagi',
        };
    }

    private static function level(int $daysLeft): string
    {
        return match (true) {
            $daysLeft < 0 => 'expired',
            $daysLeft <= self::CRITICAL_DAYS => 'critical',
            $daysLeft <= self::WARNING_DAYS => 'warning',
            default => 'ok',
        };
    }
}
