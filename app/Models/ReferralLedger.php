<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only referral accounting entry. A partner's rupiah balance is always
 * SUM(amount) here — see {@see Partner::balanceAmount()} — never a column
 * updated in place. Mirrors {@see AiTokenLedger}.
 */
final class ReferralLedger extends Model
{
    public const TYPE_EARN = 'earn';

    public const TYPE_VOID = 'void';

    public const TYPE_WITHDRAW = 'withdraw';

    protected $table = 'referral_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
