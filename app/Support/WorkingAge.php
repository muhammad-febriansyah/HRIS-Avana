<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The minimum age an employee record may claim.
 *
 * UU 13/2003 puts the working age at 18, with 15–17 allowed only as light work
 * under strict conditions; the HR data that matters here is the floor the
 * company accepts, and the client's rule is 17. Keeping it in one place means
 * the admin form, the ESS profile, the mobile profile and the data-change
 * request all refuse the same birth dates.
 */
final class WorkingAge
{
    public const MINIMUM_YEARS = 17;

    /**
     * The latest birth date that still clears the minimum age.
     */
    public static function latestBirthDate(): string
    {
        return Carbon::parse(today()->toDateString())
            ->subYears(self::MINIMUM_YEARS)
            ->toDateString();
    }

    /**
     * The birth-date rules to append to a validator.
     *
     * @return array<int, string>
     */
    public static function birthDateRules(): array
    {
        return ['before_or_equal:'.self::latestBirthDate()];
    }

    public static function message(): string
    {
        return 'Umur minimal '.self::MINIMUM_YEARS.' tahun.';
    }
}
