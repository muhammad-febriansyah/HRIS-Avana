<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An operational float paid out to an employee up front and accounted for
 * afterwards through a {@see Settlement}. Salary-deducted lending lives in
 * {@see Loan} instead.
 */
final class CashAdvance extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'request_date' => 'date',
            'needed_date' => 'date',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'settled_at' => 'datetime',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(Settlement::class);
    }

    /**
     * Only a disbursed advance can be accounted for, and only once.
     */
    public function isSettleable(): bool
    {
        return $this->status === 'disbursed';
    }
}
