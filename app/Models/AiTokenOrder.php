<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Pakasir-paid purchase of an {@see AiTokenPack}. The wallet is credited once
 * payment is verified, guarded by `credited_at`.
 *
 * {@see SCOPE_TENANT} orders top up the company wallet everyone draws on;
 * {@see SCOPE_USER} orders top up the buyer's own, which no cap rations.
 */
final class AiTokenOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** Tokens land on the company wallet, shared by the whole tenant. */
    public const SCOPE_TENANT = 'tenant';

    /** Tokens land on the wallet of the person who bought them. */
    public const SCOPE_USER = 'user';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_amount' => 'integer',
            'amount' => 'integer',
            'completed_at' => 'datetime',
            'credited_at' => 'datetime',
            'raw_payload' => 'array',
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
     * @return BelongsTo<AiTokenPack, $this>
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(AiTokenPack::class, 'ai_token_pack_id');
    }

    /**
     * @param  Builder<AiTokenOrder>  $query
     * @return Builder<AiTokenOrder>
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
