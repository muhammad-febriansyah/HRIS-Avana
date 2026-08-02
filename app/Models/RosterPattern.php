<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rotation the roster can be filled from: an ordered cycle of steps that
 * repeats for as long as it is applied.
 */
final class RosterPattern extends Model
{
    protected $guarded = [];

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RosterPatternStep::class)->orderBy('position');
    }

    /**
     * How many days one full turn of the cycle covers.
     */
    public function cycleDays(): int
    {
        return (int) $this->steps->sum('days');
    }

    /**
     * The cycle written the way the client's template table writes it —
     * "3M – 3A – 3N – 2O".
     */
    public function summary(): string
    {
        if ($this->steps->isEmpty()) {
            return '—';
        }

        return $this->steps
            ->map(fn (RosterPatternStep $step): string => $step->days.($step->shift?->code ?? 'O'))
            ->implode(' – ');
    }
}
