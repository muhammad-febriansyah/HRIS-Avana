<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "daftar perusahaan" inquiry submitted through a partner's referral link
 * (or, unattributed, straight from the public form). Converting one into a
 * client happens from the super admin's Klien wizard, which is what stamps
 * `tenants.partner_id` and starts the commission clock.
 */
final class ReferralLead extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_LOST = 'lost';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'converted_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_CONVERTED, self::STATUS_LOST]);
    }
}
