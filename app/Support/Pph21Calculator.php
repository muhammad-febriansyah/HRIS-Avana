<?php

namespace App\Support;

/**
 * Dispatches the monthly PPh 21 withholding across every income-recipient
 * subject of PP 58/2023 & PMK 168/2023, matching the official "Resume Skema"
 * (Setup TER PPh21):
 *
 *   1 Pegawai Tetap & Pensiunan     → Bruto × TER Bulanan
 *   2 PNS/TNI/Polri/Pejabat Negara  → Bruto × TER Bulanan (masa terakhir: PKP × Pasal 17)
 *   3 Dewan Komisaris/Pengawas      → Bruto × TER Bulanan (per masa pajak)
 *   4 Pegawai Tidak Tetap           → harian ≤2,5jt: Bruto Sehari × TER Harian;
 *                                      harian >2,5jt: Bruto × 50% × Pasal 17;
 *                                      bulanan:       Bruto × TER Bulanan
 *   5 Bukan Pegawai                 → Bruto × 50% × Pasal 17
 *   6 Peserta Kegiatan              → Bruto × Pasal 17
 *   7 Peserta Program Pensiun       → Bruto × Pasal 17
 *   8 Mantan Pegawai                → Bruto × Pasal 17
 *
 * The Pasal 17 progressive tariff is injected as a callable so this class stays
 * pure and unit-testable (the payroll engine passes its tenant-configurable
 * bracket resolver). December / final-month annual reconciliation for pegawai
 * tetap & PNS is handled by the caller, not here.
 */
final class Pph21Calculator
{
    /**
     * Every supported subject key, in the order of the official resume.
     *
     * @var array<int, string>
     */
    public const SUBJECTS = [
        'pegawai_tetap',
        'pns',
        'komisaris',
        'pegawai_tidak_tetap',
        'bukan_pegawai',
        'peserta_kegiatan',
        'peserta_pensiun',
        'mantan_pegawai',
    ];

    /**
     * Subjects whose regular months use the monthly TER table.
     *
     * @var array<int, string>
     */
    private const TER_MONTHLY = ['pegawai_tetap', 'pns', 'komisaris'];

    /**
     * Subjects taxed on the full gross at the progressive Pasal 17 tariff.
     *
     * @var array<int, string>
     */
    private const PASAL17_FULL = ['peserta_kegiatan', 'peserta_pensiun', 'mantan_pegawai'];

    /**
     * A daily wage above this is not withheld via TER Harian — 50% × Pasal 17.
     */
    private const DAILY_THRESHOLD = 2_500_000.0;

    /**
     * Compute the month's PPh 21 for a subject.
     *
     * `effective_on` picks the TER table in force on that date — the payroll
     * period's own date, so re-running an old month resolves the tariff that
     * applied then rather than whatever was entered since.
     *
     * @param  array{wage_basis?: string, daily_wage?: float|null, effective_on?: string|null}  $opts
     * @param  callable(float): float  $progressive  Pasal 17 layered tax on a base amount
     * @return array{amount: float, method: string, subject: string, ptkp_status: ?string, ter_category: string, ter_rate: ?float, base: float, gross: float}
     */
    public static function compute(string $subject, ?string $ptkpStatus, float $monthlyGross, array $opts, callable $progressive): array
    {
        $gross = max(0.0, $monthlyGross);
        $on = $opts['effective_on'] ?? null;
        $subject = in_array($subject, self::SUBJECTS, true) ? $subject : 'pegawai_tetap';

        $method = 'ter_bulanan';
        $rate = null;
        $base = $gross;
        // Only the TER paths read the PTKP mapping, and they read it strictly:
        // an unmapped status stops the calculation instead of silently
        // withholding at Kategori A. The Pasal 17 paths never look it up.
        $category = '-';

        if (in_array($subject, self::TER_MONTHLY, true)) {
            $category = Pph21Ter::categoryOrFail($ptkpStatus, $on);
            $rate = Pph21Ter::monthlyRate($category, $gross, $on);
            $amount = round($gross * $rate);
        } elseif ($subject === 'bukan_pegawai') {
            $method = '50pct_pasal17';
            $base = 0.5 * $gross;
            $amount = round($progressive($base));
        } elseif (in_array($subject, self::PASAL17_FULL, true)) {
            $method = 'pasal17';
            $amount = round($progressive($gross));
        } else { // pegawai_tidak_tetap
            $basis = ($opts['wage_basis'] ?? 'monthly') === 'daily' ? 'daily' : 'monthly';

            if ($basis === 'daily') {
                $daily = (float) ($opts['daily_wage'] ?? 0.0);
                if ($daily <= 0.0) {
                    $daily = $gross;
                }

                if ($daily <= self::DAILY_THRESHOLD) {
                    $method = 'ter_harian';
                    $rate = Pph21Ter::dailyRate($daily, $on);
                    $amount = round($gross * $rate);
                } else {
                    $method = '50pct_pasal17';
                    $base = 0.5 * $gross;
                    $amount = round($progressive($base));
                }
            } else {
                $category = Pph21Ter::categoryOrFail($ptkpStatus, $on);
                $rate = Pph21Ter::monthlyRate($category, $gross, $on);
                $amount = round($gross * $rate);
            }
        }

        return [
            'amount' => $amount,
            'method' => $method,
            'subject' => $subject,
            'ptkp_status' => $ptkpStatus,
            'ter_category' => $category,
            'ter_rate' => $rate,
            'base' => round($base),
            'gross' => round($gross),
        ];
    }

    /**
     * Whether a subject is withheld on the calendar month's bruto via the
     * monthly TER table.
     *
     * The distinction matters for weekly and biweekly payroll: monthly TER is a
     * rate on the *month's* income, so each run within a month has to be
     * charged on the month to date, whereas a daily wage or a Pasal 17 subject
     * is taxed per masa pajak on its own.
     */
    public static function usesMonthlyTer(string $subject, string $wageBasis = 'monthly'): bool
    {
        return in_array($subject, self::TER_MONTHLY, true)
            || ($subject === 'pegawai_tidak_tetap' && $wageBasis !== 'daily');
    }

    /**
     * Whether a subject's regular months use monthly TER and therefore need a
     * December / final-month annual Pasal 17 reconciliation.
     */
    public static function needsAnnualReconciliation(string $subject): bool
    {
        return in_array($subject, ['pegawai_tetap', 'pns'], true);
    }
}
