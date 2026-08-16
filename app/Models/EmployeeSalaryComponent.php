<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeSalaryComponent extends Model
{
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'employee_contract_id',
        'salary_master_id',
        'salary_change_set_id',
        'source_type',
        'payroll_component_id',
        'amount',
        // What this version replaced, so a raise reads "from → to" on one row.
        'previous_amount',
        'status',
        'effective_start_date',
        'effective_end_date',
        'reason',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    protected $attributes = [
        'source_type' => 'employee_override',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'previous_amount' => 'decimal:2',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * The rows in force on a date. A salary change closes the row it replaces
     * and opens a new one, so history is kept without ever paying two amounts
     * for the same component; rows with no dates are open-ended and always
     * count, which is what every row written before effective dating existed
     * looks like.
     */
    public function scopeEffectiveOn(Builder $query, string|CarbonInterface|null $date = null): Builder
    {
        $on = $date instanceof CarbonInterface ? $date->toDateString() : ($date ?? now()->toDateString());

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_start_date')->orWhereDate('effective_start_date', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('effective_end_date')->orWhereDate('effective_end_date', '>=', $on));
    }

    /**
     * Only the versions that actually pay. A row still waiting for approval, or
     * one that was cancelled, is history — never money.
     */
    public function scopeInForce(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('status')->orWhere('status', 'active'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'employee_contract_id');
    }

    public function salaryMaster(): BelongsTo
    {
        return $this->belongsTo(SalaryMaster::class);
    }

    public function changeSet(): BelongsTo
    {
        return $this->belongsTo(SalaryChangeSet::class, 'salary_change_set_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
