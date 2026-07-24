<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-saved Report Studio pivot definition, so tenants can build and reuse
 * their own reports without engineering involvement.
 */
final class ReportStudioReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
