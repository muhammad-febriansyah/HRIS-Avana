<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide referral commission rate, edited in place
 * from the super admin's Referral > Pengaturan Komisi screen.
 */
final class ReferralSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'percent_rate' => 'decimal:2',
            'hold_days' => 'integer',
            'min_withdrawal_amount' => 'decimal:2',
            'withdrawal_enabled' => 'boolean',
            'leads_tab_enabled' => 'boolean',
            'komisi_tab_enabled' => 'boolean',
            'rekening_tab_enabled' => 'boolean',
            'klien_tab_enabled' => 'boolean',
        ];
    }

    /**
     * The singleton settings row, created on first access.
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'percent_rate' => 0,
            'hold_days' => 14,
            'min_withdrawal_amount' => 0,
            'withdrawal_enabled' => true,
            'leads_tab_enabled' => true,
            'komisi_tab_enabled' => true,
            'rekening_tab_enabled' => true,
            'klien_tab_enabled' => false,
        ]);
    }

    /**
     * Calculate the commission amount from the qualifying invoice total using
     * a partner override when present, otherwise the platform-wide rate.
     */
    public function resolveCommission(?Partner $partner = null, float $baseAmount = 0): float
    {
        $rate = $partner?->commission_value !== null ? (float) $partner->commission_value : (float) $this->percent_rate;

        return round($baseAmount * $rate / 100, 2);
    }
}
