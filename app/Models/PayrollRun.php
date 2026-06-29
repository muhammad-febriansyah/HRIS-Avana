<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PayrollRun extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  array<int, int|string>  $branchIds
     */
    public function scopeForBranches(Builder $query, array $branchIds): Builder
    {
        return $query->whereIn('branch_id', $branchIds);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }
}
