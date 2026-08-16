<?php

namespace App\Support;

use App\Exceptions\Pph21ConfigurationException;
use App\Models\Pph21TerCategory;
use App\Models\Pph21TerRate;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Monthly PPh 21 withholding using the Tarif Efektif Rata-rata (TER) scheme
 * mandated by PP 58/2023 & PMK 168/2023 (effective 1 January 2024).
 *
 * For January–November the monthly deduction is a flat effective rate applied
 * to the month's gross (bruto) taxable income; the rate is looked up from the
 * TER bracket table for the employee's category. December (or an employee's
 * final month) is reconciled against the progressive Pasal 17 tariff — handled
 * by the caller, not here.
 *
 * The employee's category is derived from PTKP status:
 *   - Kategori A: TK/0, TK/1, K/0
 *   - Kategori B: TK/2, TK/3, K/1, K/2
 *   - Kategori C: K/3
 *
 * **The rates are master data.** Both the brackets and the PTKP→category
 * mapping are read from `pph21_ter_rates` / `pph21_ter_categories`, each row
 * dated, so a new PMK is entered on the Tarif TER screen rather than shipped in
 * a release — and a payroll run for an earlier month still resolves the table
 * that was in force then. The constants below are the PP 58/2023 tables as
 * enacted: they seed the master and stand in whenever no row covers a date, so
 * a fresh install or an emptied table still withholds the statutory amount
 * instead of nothing. They have been validated end-to-end against the client's
 * official "Setup TER PPh21" workbook — the full range matches.
 *
 * Each bracket row is [upper bound of monthly bruto (inclusive), effective
 * rate]; the final row uses PHP_INT_MAX for "and above".
 */
final class Pph21Ter
{
    /**
     * The categories the scheme recognises. A/B/C are the monthly (bulanan)
     * tables; HARIAN is the daily one.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'A' => 'Kategori A — TK/0, TK/1, K/0',
        'B' => 'Kategori B — TK/2, TK/3, K/1, K/2',
        'C' => 'Kategori C — K/3',
        'HARIAN' => 'TER Harian — pegawai tidak tetap harian',
    ];

    /**
     * Resolved tables, memoised per (category, date) for the request. A payroll
     * run asks once per employee and the master cannot change mid-run.
     *
     * @var array<string, list<array{0: int|float, 1: float}>>
     */
    private static array $bracketCache = [];

    /**
     * @var array<string, array<string, string>>
     */
    private static array $categoryMapCache = [];

    /**
     * Drop the memoised tables — after editing the master, and in tests.
     */
    public static function forget(): void
    {
        self::$bracketCache = [];
        self::$categoryMapCache = [];
    }

    /**
     * The PP 58/2023 monthly tables as enacted, keyed by category.
     *
     * @return array<string, list<array{0: int, 1: float}>>
     */
    public static function statutoryMonthly(): array
    {
        return ['A' => self::CATEGORY_A, 'B' => self::CATEGORY_B, 'C' => self::CATEGORY_C];
    }

    /**
     * The PP 58/2023 daily table as enacted.
     *
     * @return list<array{0: int, 1: float}>
     */
    public static function statutoryDaily(): array
    {
        return self::DAILY;
    }

    /**
     * The PMK 168/2023 PTKP→category mapping as enacted.
     *
     * @return array<string, string>
     */
    public static function statutoryCategoryMap(): array
    {
        return [
            'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
            'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
            'K/3' => 'C',
        ];
    }

    /**
     * TER Kategori A — PTKP TK/0 (54jt), TK/1 & K/0 (58,5jt). 42 brackets.
     *
     * @var list<array{0: int, 1: float}>
     */
    private const CATEGORY_A = [
        [5_400_000, 0.0], [5_650_000, 0.0025], [5_950_000, 0.005], [6_300_000, 0.0075],
        [6_750_000, 0.01], [7_500_000, 0.0125], [8_550_000, 0.015], [9_650_000, 0.0175],
        [10_050_000, 0.02], [10_350_000, 0.0225], [10_700_000, 0.025], [11_050_000, 0.0275],
        [11_600_000, 0.03], [12_500_000, 0.0325], [13_750_000, 0.035], [15_100_000, 0.0375],
        [16_950_000, 0.04], [19_750_000, 0.0425], [24_150_000, 0.045], [26_450_000, 0.0475],
        [28_000_000, 0.05], [30_050_000, 0.0525], [32_400_000, 0.055], [35_400_000, 0.0575],
        [39_100_000, 0.06], [43_850_000, 0.0625], [47_800_000, 0.065], [51_400_000, 0.0675],
        [56_300_000, 0.07], [62_200_000, 0.0725], [68_600_000, 0.075], [77_500_000, 0.0775],
        [89_000_000, 0.08], [103_000_000, 0.085], [125_000_000, 0.09], [157_000_000, 0.095],
        [206_000_000, 0.10], [337_000_000, 0.15], [454_000_000, 0.20], [550_000_000, 0.25],
        [1_400_000_000, 0.30], [PHP_INT_MAX, 0.34],
    ];

    /**
     * TER Kategori B — PTKP TK/2 & K/1 (63jt), TK/3 & K/2 (67,5jt). 39 brackets.
     *
     * @var list<array{0: int, 1: float}>
     */
    private const CATEGORY_B = [
        [6_200_000, 0.0], [6_500_000, 0.0025], [6_850_000, 0.005], [7_300_000, 0.0075],
        [9_200_000, 0.01], [10_750_000, 0.0125], [11_250_000, 0.015], [11_600_000, 0.0175],
        [12_600_000, 0.02], [13_600_000, 0.0225], [14_950_000, 0.025], [16_400_000, 0.0275],
        [18_450_000, 0.03], [21_850_000, 0.0325], [26_000_000, 0.035], [27_700_000, 0.0375],
        [29_350_000, 0.04], [31_450_000, 0.0425], [33_950_000, 0.045], [37_100_000, 0.0475],
        [41_100_000, 0.05], [45_800_000, 0.0525], [49_500_000, 0.055], [53_800_000, 0.0575],
        [58_500_000, 0.06], [64_000_000, 0.0625], [71_000_000, 0.065], [80_000_000, 0.0675],
        [93_000_000, 0.07], [109_000_000, 0.0725], [129_000_000, 0.075], [163_000_000, 0.08],
        [211_000_000, 0.085], [374_000_000, 0.09], [459_000_000, 0.15], [555_000_000, 0.20],
        [704_000_000, 0.25], [1_405_000_000, 0.30], [PHP_INT_MAX, 0.34],
    ];

    /**
     * TER Kategori C — PTKP K/3 (72jt). 41 brackets.
     *
     * @var list<array{0: int, 1: float}>
     */
    private const CATEGORY_C = [
        [6_600_000, 0.0], [6_950_000, 0.0025], [7_350_000, 0.005], [7_800_000, 0.0075],
        [8_850_000, 0.01], [9_800_000, 0.0125], [10_950_000, 0.015], [11_200_000, 0.0175],
        [12_050_000, 0.02], [12_950_000, 0.0225], [14_150_000, 0.025], [15_550_000, 0.0275],
        [17_050_000, 0.03], [19_500_000, 0.0325], [22_700_000, 0.035], [26_600_000, 0.0375],
        [28_100_000, 0.04], [30_100_000, 0.0425], [32_600_000, 0.045], [35_400_000, 0.0475],
        [38_900_000, 0.05], [43_000_000, 0.0525], [47_400_000, 0.055], [51_200_000, 0.0575],
        [55_800_000, 0.06], [60_400_000, 0.0625], [66_700_000, 0.065], [74_500_000, 0.0675],
        [83_200_000, 0.07], [95_600_000, 0.0725], [110_000_000, 0.075], [134_000_000, 0.08],
        [169_000_000, 0.085], [221_000_000, 0.09], [390_000_000, 0.095], [463_000_000, 0.10],
        [561_000_000, 0.15], [709_000_000, 0.20], [1_020_000_000, 0.25], [1_415_000_000, 0.30],
        [PHP_INT_MAX, 0.34],
    ];

    /**
     * TER Harian (daily) brackets — PMK 168/2023: bruto harian s.d. Rp450.000
     * is 0%, above that up to Rp2.500.000/day is 0,5%. A daily wage over
     * Rp2.500.000 is NOT withheld via TER Harian — the caller applies
     * 50% × Tarif Pasal 17 instead.
     *
     * @var list<array{0: int, 1: float}>
     */
    private const DAILY = [
        [450_000, 0.0],
        [2_500_000, 0.005],
    ];

    /**
     * The effective daily TER rate for a single day's gross wage (≤ Rp2,5jt),
     * from the table in force on `$on` (today when omitted).
     */
    public static function dailyRate(float $dailyGross, string|CarbonInterface|null $on = null): float
    {
        $gross = max(0.0, $dailyGross);

        foreach (self::bracketsFor('HARIAN', $on) as [$upTo, $rate]) {
            if ($gross <= $upTo) {
                return $rate;
            }
        }

        return 0.005;
    }

    /**
     * The eight PTKP statuses the TER scheme recognises. A dependant count is
     * capped at three, so nothing outside this list is a legal status.
     *
     * @var array<int, string>
     */
    public const PTKP_STATUSES = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];

    /**
     * Resolve the TER category (A/B/C) for a PTKP status code such as "TK/0",
     * using the mapping in force on `$on`. Unknown or empty statuses default to
     * A (the lowest-allowance category).
     *
     * Payroll must not take this default silently — a status nobody mapped is a
     * configuration hole, not a category-A employee — so the payroll engine
     * calls {@see self::categoryOrFail()} instead.
     */
    public static function category(?string $ptkpStatus, string|CarbonInterface|null $on = null): string
    {
        return self::categoryMap($on)[self::normaliseStatus($ptkpStatus)] ?? 'A';
    }

    /**
     * The TER category for a PTKP status, refusing to guess.
     *
     * @throws Pph21ConfigurationException when the status is empty or the
     *                                     mapping in force does not cover it
     */
    public static function categoryOrFail(?string $ptkpStatus, string|CarbonInterface|null $on = null): string
    {
        $status = self::normaliseStatus($ptkpStatus);

        if ($status === '') {
            throw new Pph21ConfigurationException('Status PTKP belum diisi.');
        }

        $category = self::categoryMap($on)[$status] ?? null;

        if ($category === null) {
            throw new Pph21ConfigurationException(
                "Status PTKP {$status} tidak ada di mapping Kategori TER yang berlaku pada ".self::dateKey($on).'.',
            );
        }

        return $category;
    }

    /**
     * Whether the mapping in force on a date covers a PTKP status.
     */
    public static function hasCategory(?string $ptkpStatus, string|CarbonInterface|null $on = null): bool
    {
        $status = self::normaliseStatus($ptkpStatus);

        return $status !== '' && isset(self::categoryMap($on)[$status]);
    }

    /**
     * "tk / 0" and "TK-0" are the same status as "TK/0" to a human typing it.
     */
    private static function normaliseStatus(?string $ptkpStatus): string
    {
        return str_replace([' ', '-'], '', strtoupper(trim((string) $ptkpStatus)));
    }

    /**
     * The effective monthly TER rate (as a decimal, e.g. 0.02 = 2%) for a gross
     * monthly taxable income in the given category, from the table in force on
     * `$on` (today when omitted).
     */
    public static function monthlyRate(string $category, float $monthlyGross, string|CarbonInterface|null $on = null): float
    {
        $gross = max(0.0, $monthlyGross);

        foreach (self::bracketsFor($category, $on) as [$upTo, $rate]) {
            if ($gross <= $upTo) {
                return $rate;
            }
        }

        return 0.34;
    }

    /**
     * The bracket table for a category on a date: the master rows in force,
     * falling back to the statutory table when the master says nothing.
     *
     * @return list<array{0: int|float, 1: float}>
     */
    public static function bracketsFor(string $category, string|CarbonInterface|null $on = null): array
    {
        $key = strtoupper($category);
        $key = array_key_exists($key, self::CATEGORIES) ? $key : 'A';
        $date = self::dateKey($on);

        return self::$bracketCache[$key.'|'.$date] ??= self::loadBrackets($key, $date);
    }

    /**
     * @return list<array{0: int|float, 1: float}>
     */
    private static function loadBrackets(string $category, string $date): array
    {
        if (! self::tableExists('pph21_ter_rates')) {
            return self::statutoryFallback($category);
        }

        $effective = Pph21TerRate::query()->where('category', $category)->effectiveOn($date);

        // Two sets can overlap if one is published without closing the other.
        // The newest published table wins rather than the two interleaving into
        // a tariff nobody wrote. The max is re-formatted because SQLite hands
        // back "2027-07-01 00:00:00" where MySQL returns a bare date.
        $latest = (clone $effective)->max('effective_start_date');
        $latest = $latest !== null ? Carbon::parse($latest)->toDateString() : null;

        $rows = $effective
            ->when(
                $latest !== null,
                fn ($query) => $query->whereDate('effective_start_date', $latest),
                fn ($query) => $query->whereNull('effective_start_date'),
            )
            ->orderByRaw('income_max IS NULL')
            ->orderBy('income_max')
            ->get(['income_max', 'rate']);

        if ($rows->isEmpty()) {
            return self::statutoryFallback($category);
        }

        return $rows
            ->map(fn (Pph21TerRate $row): array => [
                $row->income_max !== null ? (float) $row->income_max : PHP_INT_MAX,
                (float) $row->rate,
            ])
            ->all();
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    private static function statutoryFallback(string $category): array
    {
        return match ($category) {
            'B' => self::CATEGORY_B,
            'C' => self::CATEGORY_C,
            'HARIAN' => self::DAILY,
            default => self::CATEGORY_A,
        };
    }

    /**
     * The PTKP→category mapping in force on a date, statutory when unset.
     *
     * @return array<string, string>
     */
    public static function categoryMap(string|CarbonInterface|null $on = null): array
    {
        $date = self::dateKey($on);

        return self::$categoryMapCache[$date] ??= self::loadCategoryMap($date);
    }

    /**
     * @return array<string, string>
     */
    private static function loadCategoryMap(string $date): array
    {
        if (! self::tableExists('pph21_ter_categories')) {
            return self::statutoryCategoryMap();
        }

        // Newest wins per status, so a status moved to another category is not
        // read from the row it replaced.
        $rows = Pph21TerCategory::query()
            ->effectiveOn($date)
            ->orderByRaw('effective_start_date IS NULL')
            ->orderBy('effective_start_date')
            ->orderBy('id')
            ->get(['ptkp_status', 'category'])
            ->pluck('category', 'ptkp_status')
            ->all();

        return $rows === [] ? self::statutoryCategoryMap() : $rows;
    }

    /**
     * Normalise the "as of" argument to a Y-m-d string, defaulting to today.
     */
    private static function dateKey(string|CarbonInterface|null $on): string
    {
        if ($on instanceof CarbonInterface) {
            return $on->toDateString();
        }

        return $on !== null && $on !== '' ? Carbon::parse($on)->toDateString() : Carbon::now()->toDateString();
    }

    /**
     * Whether the master table is present. The support class is used by
     * migrations and by early-boot code where the schema may not exist yet, so
     * a missing table means "use the statutory numbers", never a crash.
     */
    private static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }
}
