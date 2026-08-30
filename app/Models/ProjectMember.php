<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee assigned to a project. The assignment is what lets that employee
 * log hours against the project from the phone, and it carries the rate
 * overrides that beat the project's defaults when the entry is costed.
 */
final class ProjectMember extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bill_rate' => 'decimal:2',
            'cost_rate' => 'decimal:2',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
