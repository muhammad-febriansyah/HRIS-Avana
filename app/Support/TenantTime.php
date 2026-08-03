<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * The wall clock a tenant works to.
 *
 * Times are stored as the wall clock they were taken on, which is what the
 * application has always done — the zone decides which wall. A Makassar office
 * clocking in at 08:00 records 08:00 and is not late for an 08:00 shift, where
 * one global WIB clock recorded it as 07:00.
 *
 * Because storage already holds the tenant's own hour, reading a stored time
 * back needs no conversion; only "now" and "today" have to be asked for.
 *
 * Every reader goes through here rather than calling `now()` directly, so
 * "today", "is this late", and "what time does this read as" all agree.
 */
final class TenantTime
{
    /**
     * The three Indonesian zones, as the label a tenant would recognise.
     *
     * @var array<string, string>
     */
    public const ZONES = [
        'Asia/Jakarta' => 'WIB — Waktu Indonesia Barat (UTC+7)',
        'Asia/Makassar' => 'WITA — Waktu Indonesia Tengah (UTC+8)',
        'Asia/Jayapura' => 'WIT — Waktu Indonesia Timur (UTC+9)',
    ];

    /**
     * Short label for a zone, for a column heading or a chip.
     */
    public static function shortLabel(?string $zone): string
    {
        return match ($zone) {
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => 'WIB',
        };
    }

    /**
     * Resolved zones, keyed by tenant id, for the life of the request.
     *
     * @var array<int, string>
     */
    private static array $cache = [];

    /**
     * Resolved branch zones (null = the branch names none), keyed "b{id}".
     *
     * @var array<string, string|null>
     */
    private static array $branchCache = [];

    /**
     * The tenant's zone, falling back to the application's own when the tenant
     * is unknown or has never set one.
     */
    public static function zone(int|string|null $tenantId): string
    {
        $fallback = (string) config('app.timezone', 'Asia/Jakarta');

        if ($tenantId === null) {
            return $fallback;
        }

        $key = (int) $tenantId;

        if (! array_key_exists($key, self::$cache)) {
            $zone = Tenant::withTrashed()->whereKey($key)->value('timezone');

            self::$cache[$key] = is_string($zone) && $zone !== '' && self::isValid($zone)
                ? $zone
                : $fallback;
        }

        return self::$cache[$key];
    }

    /**
     * The zone an employee actually works in.
     *
     * A branch may sit in a different zone from the head office — a Jakarta
     * company with a Makassar warehouse is one tenant with two wall clocks —
     * so the branch answers first when it names one. `branches.timezone` has
     * been a writable column all along; this is what reads it.
     */
    public static function zoneForBranch(int|string|null $tenantId, int|string|null $branchId): string
    {
        if ($branchId === null) {
            return self::zone($tenantId);
        }

        $key = 'b'.$branchId;

        if (! array_key_exists($key, self::$branchCache)) {
            $zone = Branch::withTrashed()->whereKey($branchId)->value('timezone');

            self::$branchCache[$key] = is_string($zone) && $zone !== '' && self::isValid($zone)
                ? $zone
                : null;
        }

        return self::$branchCache[$key] ?? self::zone($tenantId);
    }

    /**
     * Now, on the tenant's wall clock.
     */
    public static function now(int|string|null $tenantId): Carbon
    {
        return Carbon::now(self::zone($tenantId));
    }

    /**
     * Today's date on the tenant's wall clock, as `Y-m-d`.
     */
    public static function today(int|string|null $tenantId): string
    {
        return self::now($tenantId)->toDateString();
    }

    /**
     * Midnight today on the tenant's wall clock.
     *
     * The date part of this is what "today" means to the office reading the
     * screen. A Jayapura tenant at half past midnight is on a new day the
     * server, still on the previous WIB evening, would put a day behind.
     */
    public static function startOfToday(int|string|null $tenantId): Carbon
    {
        return self::now($tenantId)->startOfDay();
    }

    /**
     * Whether a zone name is one PHP actually knows.
     */
    public static function isValid(string $zone): bool
    {
        return in_array($zone, timezone_identifiers_list(), true);
    }

    /**
     * Drop the resolved-zone cache — for tests, and after a tenant changes it.
     */
    public static function forget(): void
    {
        self::$cache = [];
        self::$branchCache = [];
    }
}
