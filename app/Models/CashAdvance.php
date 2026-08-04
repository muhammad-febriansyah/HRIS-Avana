<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operational float paid out to an employee up front. Salary-deducted
 * lending lives in {@see Loan} instead.
 */
final class CashAdvance extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'topup_amount' => 'decimal:2',
            'request_date' => 'date',
            'needed_date' => 'date',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * Split what was actually spent against what was handed over.
     *
     * Spending less leaves money to return; spending more leaves the company
     * owing the difference. Exactly one of the two is ever non-zero.
     *
     * @return array{returned: float, topup: float}
     */
    public static function reconcile(float $advanced, float $spent): array
    {
        $difference = round($advanced - $spent, 2);

        return [
            'returned' => max($difference, 0),
            'topup' => max(-$difference, 0),
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

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
