<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PayrollComponent extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_fixed' => 'boolean',
            'show_on_slip' => 'boolean',
            'basis_value' => 'decimal:2',
            'basis_min' => 'decimal:2',
            'basis_max' => 'decimal:2',
            'basis_cut_off_day' => 'integer',
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

    public function salaryComponents(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function positionComponents(): HasMany
    {
        return $this->hasMany(PositionPayrollComponent::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(PayrollFormula::class, 'payroll_formula_id');
    }

    public function componentValues(): HasMany
    {
        return $this->hasMany(PayrollComponentValue::class);
    }
}
