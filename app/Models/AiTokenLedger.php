<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only AI token accounting entry. Source of truth for monthly usage and
 * wallet movements. `wallet_delta` is signed (negative debit, positive credit);
 * `period` buckets usage by calendar month (YYYY-MM).
 */
final class AiTokenLedger extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    protected $table = 'ai_token_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
            'wallet_delta' => 'integer',
            'balance_after' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AiTokenOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(AiTokenOrder::class, 'ai_token_order_id');
    }
}
