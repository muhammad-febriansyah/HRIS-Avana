<?php

namespace App\Support;

use App\Models\PayrollComponent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which component is "Gaji Pokok".
 *
 * The code is typed by whoever set the tenant up, so it arrives as `BASIC`,
 * `Basic` or `basic` depending on the day. Every screen that compared it with
 * `=== 'BASIC'` therefore read a basic wage of zero for half the tenants — and a
 * zero basic wage quietly breaks the UMR check, the overtime basis and the
 * contract's salary snapshot. Matched case-insensitively in one place instead.
 */
final class BasicWageComponent
{
    /** The canonical code, upper-cased. */
    public const CODE = 'BASIC';

    /** Whether a component code names the basic wage. */
    public static function matches(?string $code): bool
    {
        return mb_strtoupper(trim((string) $code)) === self::CODE;
    }

    /**
     * Constrain a query to the basic wage component, whatever case its code was
     * typed in.
     *
     * @param  Builder<PayrollComponent>  $query
     * @return Builder<PayrollComponent>
     */
    public static function scope(Builder $query): Builder
    {
        return $query->whereRaw('UPPER(code) = ?', [self::CODE]);
    }

    /** The tenant's basic wage component, or null when it has none. */
    public static function for(int $tenantId): ?PayrollComponent
    {
        return self::scope(PayrollComponent::forTenant($tenantId))->first();
    }

    /** SQL that sorts the basic wage component first. */
    public static function orderFirstSql(): string
    {
        return "UPPER(code) = '".self::CODE."' DESC";
    }
}
