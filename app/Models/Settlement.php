<?php

namespace App\Models;

use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory;

    /** The employee owes the company the leftover float. */
    public const OUTCOME_RETURN = 'return';

    /** The company owes the employee what they overspent. */
    public const OUTCOME_TOPUP = 'topup';

    /** Spend matched the advance exactly; no money moves. */
    public const OUTCOME_BALANCED = 'balanced';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'advance_amount' => 'decimal:2',
            'total_spent' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'topup_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'topup_paid_at' => 'datetime',
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

    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function returnedReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_received_by');
    }

    public function topupPaidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'topup_paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    /**
     * What is left of the advance after the receipts: positive when the
     * employee is holding leftover float, negative when they overspent.
     */
    public function balance(): float
    {
        return round((float) $this->advance_amount - (float) $this->total_spent, 2);
    }

    /**
     * Which way the money has to move to close this settlement.
     */
    public function outcome(): string
    {
        return match (true) {
            $this->balance() > 0 => self::OUTCOME_RETURN,
            $this->balance() < 0 => self::OUTCOME_TOPUP,
            default => self::OUTCOME_BALANCED,
        };
    }

    /**
     * The amount still owed in whichever direction {@see outcome} points, or
     * zero once it has been handed over.
     */
    public function outstanding(): float
    {
        return match ($this->outcome()) {
            self::OUTCOME_RETURN => round(max(0, $this->balance() - (float) $this->returned_amount), 2),
            self::OUTCOME_TOPUP => round(max(0, abs($this->balance()) - (float) $this->topup_amount), 2),
            default => 0.0,
        };
    }

    /**
     * Recalculate `total_spent` from the settlement's receipt lines.
     */
    public function recalculateTotalSpent(): void
    {
        $this->total_spent = round((float) $this->items()->sum('amount'), 2);
        $this->save();
    }
}
