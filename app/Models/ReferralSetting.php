<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide referral commission rule: single settings row, edited in place
 * from the super admin's Referral > Pengaturan Komisi screen.
 */
final class ReferralSetting extends Model
{
    public const MODE_FLAT = 'flat';

    public const MODE_PERCENT = 'percent';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points_per_conversion' => 'integer',
            'percent_rate' => 'decimal:2',
            'point_value' => 'decimal:2',
            'hold_days' => 'integer',
            'min_withdrawal_points' => 'integer',
        ];
    }

    /**
     * The singleton settings row, created on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'mode' => self::MODE_FLAT,
            'points_per_conversion' => 1,
            'percent_rate' => 0,
            'point_value' => 0,
            'hold_days' => 14,
            'min_withdrawal_points' => 0,
        ]);
    }

    /**
     * Points and rupiah commission a qualifying conversion is worth, honouring
     * a partner's own override before falling back to this global rule.
     *
     * `point_value` is always the exchange rate points are settled at, in
     * both modes — flat mode fixes the point count, percent mode derives it
     * from the invoice, but either way the rupiah owed is `points * point_value`.
     *
     * @return array{points: int, amount: float}
     */
    public function resolveCommission(float $invoiceTotal, ?Partner $partner = null): array
    {
        $mode = $partner?->commission_mode ?? $this->mode;
        $pointValue = (float) $this->point_value;

        if ($mode === self::MODE_PERCENT) {
            $rate = $partner?->commission_mode === self::MODE_PERCENT && $partner->commission_value !== null
                ? (float) $partner->commission_value
                : (float) $this->percent_rate;

            $amount = round($invoiceTotal * $rate / 100, 2);
            $points = $pointValue > 0 ? (int) floor($amount / $pointValue) : 0;

            return ['points' => $points, 'amount' => $pointValue > 0 ? round($points * $pointValue, 2) : $amount];
        }

        $points = $partner?->commission_mode === self::MODE_FLAT && $partner->commission_value !== null
            ? (int) $partner->commission_value
            : (int) $this->points_per_conversion;

        return ['points' => $points, 'amount' => round($points * $pointValue, 2)];
    }
}
