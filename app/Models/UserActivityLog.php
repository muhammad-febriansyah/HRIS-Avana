<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class UserActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string|null $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope rows to what the viewer may see: a super admin sees every tenant
     * (or one, when `$tenantId` narrows it down), a tenant user only ever sees
     * their own tenant's rows regardless of what is asked for.
     */
    public function scopeForViewer(Builder $query, User $viewer, int|string|null $tenantId = null): Builder
    {
        if (! $viewer->isSuperAdmin()) {
            return $query->where('tenant_id', $viewer->tenant_id);
        }

        return $tenantId !== null
            ? $query->where('tenant_id', $tenantId)
            : $query;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
