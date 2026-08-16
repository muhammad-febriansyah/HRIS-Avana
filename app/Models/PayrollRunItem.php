<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PayrollRunItem extends Model
{
    use HasPublicId;

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

    /**
     * Only the payslips an employee is allowed to see: those whose run is
     * locked.
     *
     * A draft/calculated/approved run is still being worked on — re-running it
     * replaces every figure — so a payslip read from one is a number the
     * employee may be paid something different from. Finance reviews those in
     * the admin screens; the employee sees a slip once it is final.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereHas('run', fn (Builder $run) => $run->where('status', PayrollRun::STATUS_LOCKED));
    }

    /**
     * Whether this payslip's run is final, so an employee may read it.
     */
    public function isPublished(): bool
    {
        return $this->run?->status === PayrollRun::STATUS_LOCKED;
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
