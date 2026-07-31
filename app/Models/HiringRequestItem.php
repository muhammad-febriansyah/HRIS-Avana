<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One manpower need inside a hiring request: a position, how many of them, and
 * the terms it is asked for under. HR raises one requisition per need.
 */
final class HiringRequestItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'vacancy' => 'integer',
            'sort_order' => 'integer',
            'target_join_date' => 'date',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function hiringRequest(): BelongsTo
    {
        return $this->belongsTo(HiringRequest::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(RecruitmentRequisition::class);
    }
}
