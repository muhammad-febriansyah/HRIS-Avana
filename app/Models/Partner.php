<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A referral partner's profile: their code, payout status, and bank details.
 * The login itself lives in `users` (role `partner`) — this table only holds
 * what is specific to being a referral partner.
 */
final class Partner extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'commission_value' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Client tenants attributed to this partner (`tenants.partner_id`), set
     * once at creation and never reassigned.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(ReferralLead::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ReferralConversion::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(ReferralLedger::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(ReferralWithdrawal::class);
    }

    /**
     * Settled rupiah balance — SUM of the append-only ledger, never a column
     * updated in place.
     */
    public function balanceAmount(): float
    {
        return (float) $this->ledger()->sum('amount');
    }

    /**
     * Balance minus whatever is reserved by a withdrawal not yet rejected
     * (pending, approved, or already paid — paid has already been debited
     * from the ledger, so it does not double-subtract here). This is what
     * blocks a partner from requesting the same balance twice while an
     * earlier request is still being processed.
     */
    public function availableAmount(): float
    {
        $reserved = (float) $this->withdrawals()
            ->whereIn('status', [ReferralWithdrawal::STATUS_PENDING, ReferralWithdrawal::STATUS_APPROVED])
            ->sum('amount');

        return $this->balanceAmount() - $reserved;
    }

    /**
     * Whether the partner has filled in enough payout details to request a
     * withdrawal. Required before any withdrawal can be submitted.
     */
    public function hasBankDetails(): bool
    {
        return filled($this->bank_name) && filled($this->bank_account_number) && filled($this->bank_account_holder);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
