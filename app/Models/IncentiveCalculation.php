<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's incentive for one scheme in one period: the amount, what it
 * was worked out from, and where it is in review.
 *
 * Only an approved (or locked) row is money. Payroll reads nothing else, so an
 * incentive nobody signed off never reaches a payslip.
 */
final class IncentiveCalculation extends Model
{
    use HasPublicId;

    /** Computed, not yet submitted for review. */
    public const STATUS_DRAFT = 'draft';

    /** Submitted and waiting on an approver. */
    public const STATUS_PENDING = 'pending';

    /** Signed off; payroll may pay it. */
    public const STATUS_APPROVED = 'approved';

    /** Turned down with a reason; payroll ignores it. */
    public const STATUS_REJECTED = 'rejected';

    /** Paid inside a locked payroll period; nothing may change it. */
    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_LOCKED,
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'measured_value' => 'decimal:2',
            'amount' => 'decimal:2',
            'computed_amount' => 'decimal:2',
            'source_snapshot' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** The rows payroll is allowed to pay. */
    public function scopePayable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_LOCKED]);
    }

    /** A locked row belongs to a period that has been paid; it is read-only. */
    public function isEditable(): bool
    {
        return $this->status !== self::STATUS_LOCKED;
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(IncentiveScheme::class, 'incentive_scheme_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
