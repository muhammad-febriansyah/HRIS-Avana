<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Timesheet extends Model
{
    use HasFactory;

    /** Filed and waiting on an approver. */
    public const STATUS_PENDING = 'pending';

    /** Approved: the hours count towards project cost and billing. */
    public const STATUS_APPROVED = 'approved';

    /** Rejected: kept for the audit trail, excluded from every total. */
    public const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'approved_at' => 'datetime',
            'is_billable' => 'boolean',
            'bill_rate' => 'decimal:2',
            'cost_rate' => 'decimal:2',
            'bill_amount' => 'decimal:2',
            'cost_amount' => 'decimal:2',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Only the entries that count towards cost, billing and the report. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** The user who approved the entry (`approved_by` is a user id). */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
