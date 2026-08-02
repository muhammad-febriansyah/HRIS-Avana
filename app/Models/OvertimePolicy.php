<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The tenant-wide overtime rule set: the hour ceilings PP 35/2021 imposes, the
 * divisor that turns a monthly wage into an hourly one, and the floor under the
 * overtime basis.
 */
final class OvertimePolicy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'max_hours_per_day' => 'decimal:2',
            'max_hours_per_week' => 'decimal:2',
            'hours_divisor' => 'integer',
            'fixed_basis_min_ratio' => 'decimal:4',
            'enforce_hour_limits' => 'boolean',
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
