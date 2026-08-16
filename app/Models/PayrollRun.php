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

    /** Figures are still being computed; a re-run replaces all of them. */
    public const STATUS_CALCULATED = 'calculated';

    /** Reviewed and signed off, but not yet final — a re-run resets approval. */
    public const STATUS_APPROVED = 'approved';

    /** Final: nothing recomputes, so this is what the employee is paid. */
    public const STATUS_LOCKED = 'locked';

    /** Figures produced by the salary engine from the data in the system. */
    public const SOURCE_ENGINE = 'engine';

    /** Figures uploaded from a file; they never passed through the engine. */
    public const SOURCE_IMPORT = 'import';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_gross' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'total_tax' => 'decimal:2',
            'total_net' => 'decimal:2',
            'employee_count' => 'integer',
            'approved_at' => 'datetime',
            'reconciliation' => 'array',
            'revision' => 'integer',
            'superseded_at' => 'datetime',
            'computed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * The revision still in play. A superseded run is history: its figures are
     * what a past payslip stated, and nothing recomputes into it.
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    /** Whether this run has been closed off by a later revision. */
    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
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
