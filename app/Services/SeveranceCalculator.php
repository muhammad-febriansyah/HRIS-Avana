<?php

namespace App\Services;

/**
 * Computes Indonesian termination benefits (pesangon) per UU Cipta Kerja and
 * PP 35/2021: severance pay (UP), long-service pay (UPMK) and compensation of
 * rights (UPH), with the reason-based multipliers applied to UP and UPMK.
 */
class SeveranceCalculator
{
    /**
     * Termination reasons mapped to their statutory multipliers.
     *
     * `up` / `upmk` are the multipliers applied to the base UP/UPMK months.
     * `separation` marks reasons that yield "uang pisah" (per policy) rather
     * than statutory severance.
     *
     * @var array<string, array{label: string, up: float, upmk: float, separation: bool}>
     */
    public const REASONS = [
        'resign' => ['label' => 'Mengundurkan diri', 'up' => 0.0, 'upmk' => 0.0, 'separation' => true],
        'phk_efisiensi_rugi' => ['label' => 'PHK efisiensi (perusahaan rugi)', 'up' => 0.5, 'upmk' => 1.0, 'separation' => false],
        'phk_efisiensi_cegah_rugi' => ['label' => 'PHK efisiensi (mencegah rugi)', 'up' => 1.0, 'upmk' => 1.0, 'separation' => false],
        'phk_biasa' => ['label' => 'PHK (umum)', 'up' => 1.0, 'upmk' => 1.0, 'separation' => false],
        'phk_pelanggaran' => ['label' => 'PHK karena pelanggaran (setelah SP)', 'up' => 0.5, 'upmk' => 1.0, 'separation' => false],
        'pensiun' => ['label' => 'Pensiun', 'up' => 1.75, 'upmk' => 1.0, 'separation' => false],
        'sakit_berkepanjangan' => ['label' => 'Sakit berkepanjangan / cacat kerja', 'up' => 2.0, 'upmk' => 2.0, 'separation' => false],
        'meninggal' => ['label' => 'Meninggal dunia', 'up' => 2.0, 'upmk' => 1.0, 'separation' => false],
    ];

    /**
     * Compute the settlement breakdown for one employee.
     *
     * @return array{
     *     reason: string,
     *     reason_label: string,
     *     tenure_years: float,
     *     monthly_wage: float,
     *     up_months: int,
     *     upmk_months: int,
     *     up_factor: float,
     *     upmk_factor: float,
     *     up_amount: float,
     *     upmk_amount: float,
     *     uph: float,
     *     separation_pay: float,
     *     total: float
     * }
     */
    public function calculate(float $monthlyWage, float $tenureYears, string $reason, float $uph = 0.0, float $separationPay = 0.0): array
    {
        $config = self::REASONS[$reason] ?? self::REASONS['phk_biasa'];

        $upMonths = $this->severanceMonths($tenureYears);
        $upmkMonths = $this->longServiceMonths($tenureYears);

        $upAmount = round($monthlyWage * $upMonths * $config['up']);
        $upmkAmount = round($monthlyWage * $upmkMonths * $config['upmk']);

        // Reasons yielding uang pisah pay no statutory UP/UPMK.
        $separation = $config['separation'] ? round($separationPay) : 0.0;

        $total = $upAmount + $upmkAmount + round($uph) + $separation;

        return [
            'reason' => $reason,
            'reason_label' => $config['label'],
            'tenure_years' => round($tenureYears, 2),
            'monthly_wage' => round($monthlyWage),
            'up_months' => $upMonths,
            'upmk_months' => $upmkMonths,
            'up_factor' => $config['up'],
            'upmk_factor' => $config['upmk'],
            'up_amount' => $upAmount,
            'upmk_amount' => $upmkAmount,
            'uph' => round($uph),
            'separation_pay' => $separation,
            'total' => $total,
        ];
    }

    /**
     * Base severance-pay months (UP) by completed years of service — PP 35/2021
     * art. 40(2): from 1 month (<1 yr) up to a 9-month ceiling (>=8 yr).
     */
    public function severanceMonths(float $tenureYears): int
    {
        return match (true) {
            $tenureYears < 1 => 1,
            $tenureYears < 2 => 2,
            $tenureYears < 3 => 3,
            $tenureYears < 4 => 4,
            $tenureYears < 5 => 5,
            $tenureYears < 6 => 6,
            $tenureYears < 7 => 7,
            $tenureYears < 8 => 8,
            default => 9,
        };
    }

    /**
     * Long-service-pay months (UPMK) by completed years of service — PP 35/2021
     * art. 40(3): none below 3 years, then 2 months rising to 10 (>=24 yr).
     */
    public function longServiceMonths(float $tenureYears): int
    {
        return match (true) {
            $tenureYears < 3 => 0,
            $tenureYears < 6 => 2,
            $tenureYears < 9 => 3,
            $tenureYears < 12 => 4,
            $tenureYears < 15 => 5,
            $tenureYears < 18 => 6,
            $tenureYears < 21 => 7,
            $tenureYears < 24 => 8,
            default => 10,
        };
    }
}
