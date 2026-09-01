<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Concerns\HasPublicId;
use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A settlement (expense claim) is money an employee spent on a business trip or
 * operational task and is claiming back. They list the expense line items, an
 * 11% tax is applied on top of the subtotal, a manager approves the amounts,
 * then Finance verifies and pays the total out to the employee's bank account.
 */
final class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory, HasPublicId;

    /** Being drafted; not yet in anyone's queue. */
    public const STATUS_DRAFT = 'draft';

    /** Submitted; waiting for the manager to approve the amounts. */
    public const STATUS_SUBMITTED = 'submitted';

    /** Manager approved; waiting for Finance to verify and pay. */
    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    /** Finance verified and disbursed the payout. */
    public const STATUS_PAID = 'paid';

    /** Sent back to the employee to fix. */
    public const STATUS_REJECTED = 'rejected';

    /** Indonesian tax applied on top of the expense subtotal. */
    public const TAX_RATE = 0.11;

    /**
     * Expense categories a settlement line may use. The five reimbursement
     * categories, plus the two that only ever show up on a business trip.
     *
     * @var array<string, string>
     */
    public const ITEM_CATEGORIES = Reimbursement::CATEGORIES + [
        'penerbangan' => 'Tiket Pesawat',
        'akomodasi' => 'Akomodasi / Hotel',
    ];

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_date' => 'date',
            'trip_start_date' => 'date',
            'trip_end_date' => 'date',
            'destination_latitude' => 'decimal:7',
            'destination_longitude' => 'decimal:7',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'manager_approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
            'fraud_checked_at' => 'datetime',
            // Snapshotted from the employee's bank account, so it carries the
            // same protection as the row it was copied from.
            'bank_account_number' => EncryptedString::class,
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

    /** The line manager this settlement is currently sitting with. */
    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_approver_id');
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function financeVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_verified_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SettlementAttachment::class);
    }

    /**
     * How many days the trip covered, counting both the departure and return
     * days. Null unless both trip dates are set.
     */
    public function tripDays(): ?int
    {
        if ($this->trip_start_date === null || $this->trip_end_date === null) {
            return null;
        }

        return $this->trip_start_date->diffInDays($this->trip_end_date) + 1;
    }

    /**
     * Recompute subtotal, 11% tax, and total from the settlement's line items.
     */
    public function recalculateTotals(): void
    {
        $subtotal = round((float) $this->items()->sum('amount'), 2);
        $tax = round($subtotal * self::TAX_RATE, 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total' => round($subtotal + $tax, 2),
        ])->save();
    }
}
