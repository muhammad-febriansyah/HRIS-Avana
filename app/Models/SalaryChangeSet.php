<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SalaryChangeSet extends Model
{
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'salary_master_id',
        'change_type',
        'existing_strategy',
        'effective_start_date',
        'status',
        'reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $attributes = [
        'change_type' => 'individual',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'effective_start_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function salaryMaster(): BelongsTo
    {
        return $this->belongsTo(SalaryMaster::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }
}
