<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the overtime multiplier table: "on a `day_type`, hours `hour_from`
 * through `hour_to` pay `multiplier` times the hourly wage". A null `hour_to`
 * means the band runs to the end of the shift.
 */
final class OvertimeRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hour_from' => 'integer',
            'hour_to' => 'integer',
            'multiplier' => 'decimal:2',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
