<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide referral commission rule: single settings row, edited in place
 * from the super admin's Referral > Pengaturan Komisi screen.
 */
final class ReferralSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'flat_amount' => 'decimal:2',
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
            'flat_amount' => 0,
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
     * The flat rupiah commission a qualifying conversion is worth — a
     * partner's own override if set, otherwise the platform-wide flat rate.
     */
    public function resolveCommission(?Partner $partner = null): float
    {
        $amount = $partner?->commission_value !== null ? (float) $partner->commission_value : (float) $this->flat_amount;

        return round($amount, 2);
    }
}
