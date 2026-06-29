<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PayrollRunItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'total_allowance' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'bpjs_employee_total' => 'decimal:2',
            'bpjs_company_total' => 'decimal:2',
            'pph21_total' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'calculation_snapshot' => 'array',
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

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
