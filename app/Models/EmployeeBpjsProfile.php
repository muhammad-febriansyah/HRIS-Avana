<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeBpjsProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'registered_wage' => 'decimal:2',
            'jht_enabled' => 'boolean',
            'jkk_enabled' => 'boolean',
            'jkm_enabled' => 'boolean',
            'jp_enabled' => 'boolean',
            'kesehatan_enabled' => 'boolean',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
