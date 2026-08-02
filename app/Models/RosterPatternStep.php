<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One leg of a rotation: a shift held for a number of consecutive days, or
 * time off when no shift is named.
 */
final class RosterPatternStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'days' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(RosterPattern::class, 'roster_pattern_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
