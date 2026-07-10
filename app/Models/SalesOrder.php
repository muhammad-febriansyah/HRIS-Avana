<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesOrder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'headcount' => 'integer',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'mapped_at' => 'datetime',
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

    public function salaryMaster(): BelongsTo
    {
        return $this->belongsTo(SalaryMaster::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
