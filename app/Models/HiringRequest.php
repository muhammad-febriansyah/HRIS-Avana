<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A manager's request for manpower. The positions themselves live in
 * {@see HiringRequestItem} — a request may carry more than one need, and HR
 * raises a requisition per need.
 */
final class HiringRequest extends Model
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HiringRequestItem::class)->orderBy('sort_order');
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(RecruitmentRequisition::class);
    }

    /**
     * Total heads asked for across every need on the request.
     */
    public function totalVacancy(): int
    {
        return (int) $this->items->sum('vacancy');
    }
}
