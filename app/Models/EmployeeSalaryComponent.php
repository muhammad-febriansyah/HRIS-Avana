<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmployeeSalaryComponent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
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
}
