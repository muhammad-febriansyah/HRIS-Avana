<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant attrition scoring configuration — factor weights and risk-band
 * cut-offs. {@see resolve()} always returns a usable instance, filling in
 * config defaults for any tenant that has not customised its settings.
 */
final class AttritionSetting extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weights' => 'array',
            'band_low' => 'integer',
            'band_medium' => 'integer',
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
     * The tenant's saved settings, or a default (unsaved) instance seeded from
     * config. Missing factor weights are backfilled from config so a newly
     * added factor is always scored.
     */
    public static function resolve(int|string $tenantId): self
    {
        $settings = self::query()->firstOrNew(['tenant_id' => $tenantId]);

        if (! $settings->exists) {
            $settings->band_low = (int) config('attrition.bands.low');
            $settings->band_medium = (int) config('attrition.bands.medium');
        }

        $settings->weights = array_merge(
            config('attrition.weights'),
            $settings->weights ?? [],
        );

        return $settings;
    }
}
