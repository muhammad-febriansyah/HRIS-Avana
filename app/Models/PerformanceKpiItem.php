<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PerformanceKpiItem extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'target_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'achievement_pct' => 'decimal:2',
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

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function kpiIndicator(): BelongsTo
    {
        return $this->belongsTo(KpiIndicator::class);
    }

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(KeyResult::class);
    }
}
