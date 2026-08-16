<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One band of a scheme: the range of measured values it covers and what that
 * band pays.
 */
final class IncentiveRule extends Model
{
    /** A flat rupiah amount for anyone in the band. */
    public const AMOUNT_FIXED = 'fixed';

    /** The amount multiplied by the measured figure (e.g. per present day). */
    public const AMOUNT_PER_UNIT = 'per_unit';

    /** A percentage of the employee's basic wage. */
    public const AMOUNT_PERCENT_OF_BASIC = 'percent_of_basic';

    public const AMOUNT_TYPES = [self::AMOUNT_FIXED, self::AMOUNT_PER_UNIT, self::AMOUNT_PERCENT_OF_BASIC];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'min_value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(IncentiveScheme::class, 'incentive_scheme_id');
    }

    /** Whether a measured figure falls in this band. */
    public function covers(float $value): bool
    {
        if ($this->min_value !== null && $value < (float) $this->min_value) {
            return false;
        }

        return ! ($this->max_value !== null && $value > (float) $this->max_value);
    }
}
