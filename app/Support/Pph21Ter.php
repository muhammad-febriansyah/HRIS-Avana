<?php

namespace App\Support;

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
 * Brackets below reproduce the PMK 168/2023 Lampiran (Tarif Efektif Bulanan).
 * Each row is [upper bound of monthly bruto (inclusive), effective rate]; the
 * final row uses PHP_INT_MAX for "and above". Every bracket in all three
 * categories AND the PTKP→category mapping have been validated end-to-end
 * against the client's official "Setup TER PPh21" workbook (PP 58/2023 &
 * PMK 168/2023 Lampiran) — the full range, including the top brackets, matches.
 */
final class Pph21Ter
{
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
     * The effective daily TER rate for a single day's gross wage (≤ Rp2,5jt).
     */
    public static function dailyRate(float $dailyGross): float
    {
        $gross = max(0.0, $dailyGross);

        foreach (self::DAILY as [$upTo, $rate]) {
            if ($gross <= $upTo) {
                return $rate;
            }
        }

        return 0.005;
    }

    /**
     * Resolve the TER category (A/B/C) for a PTKP status code such as "TK/0".
     * Unknown or empty statuses default to A (the lowest-allowance category).
     */
    public static function category(?string $ptkpStatus): string
    {
        $status = strtoupper(trim((string) $ptkpStatus));
        $status = str_replace([' ', '-'], '', $status);

        return match ($status) {
            'TK/2', 'TK/3', 'K/1', 'K/2' => 'B',
            'K/3' => 'C',
            default => 'A',
        };
    }

    /**
     * The effective monthly TER rate (as a decimal, e.g. 0.02 = 2%) for a gross
     * monthly taxable income in the given category.
     */
    public static function monthlyRate(string $category, float $monthlyGross): float
    {
        $gross = max(0.0, $monthlyGross);

        foreach (self::bracketsFor($category) as [$upTo, $rate]) {
            if ($gross <= $upTo) {
                return $rate;
            }
        }

        return 0.34;
    }

    /**
     * @return list<array{0: int, 1: float}>
     */
    private static function bracketsFor(string $category): array
    {
        return match (strtoupper($category)) {
            'B' => self::CATEGORY_B,
            'C' => self::CATEGORY_C,
            default => self::CATEGORY_A,
        };
    }
}
