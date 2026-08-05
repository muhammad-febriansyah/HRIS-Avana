<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One tier of a tenant's attendance-penalty table: a minute band and what it
 * costs. Companies word their own rules ("terlambat 10–30 menit: Rp20.000"),
 * so the bands are data rather than code.
 */
final class AttendancePenaltyRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_minutes' => 'integer',
            'max_minutes' => 'integer',
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
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

    /**
     * The tenant's active tiers for a violation, narrowest band first — so a
     * specific "10–30" wins over an open-ended "30 ke atas" when both match.
     *
     * @return Collection<int, AttendancePenaltyRule>
     */
    public static function tiersFor(int $tenantId, string $violationType = 'late'): Collection
    {
        return self::query()
            ->forTenant($tenantId)
            ->where('violation_type', $violationType)
            ->where('is_active', true)
            ->orderBy('min_minutes')
            ->get()
            ->sortBy(fn (self $rule): int => $rule->max_minutes ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * The tier covering a number of late minutes, or null when none does —
     * the minutes are inside the company's tolerance, or past its last tier.
     *
     * The band is read as "min < minutes <= max", matching how the rules are
     * written: 10–30 menit means the eleventh minute up to and including the
     * thirtieth, and 30–60 picks up from there without overlapping.
     *
     * @param  Collection<int, AttendancePenaltyRule>  $tiers
     */
    public static function match(Collection $tiers, int $minutes): ?self
    {
        foreach ($tiers as $tier) {
            if ($minutes > $tier->min_minutes && ($tier->max_minutes === null || $minutes <= $tier->max_minutes)) {
                return $tier;
            }
        }

        return null;
    }
}
