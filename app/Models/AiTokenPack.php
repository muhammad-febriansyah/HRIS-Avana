<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A super-admin-defined token pack: a fixed number of AI tokens sold for a
 * Rupiah price. Archived (soft-deleted) packs keep past orders intact.
 */
final class AiTokenPack extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'token_amount' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<AiTokenPack>  $query
     * @return Builder<AiTokenPack>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return HasMany<AiTokenOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(AiTokenOrder::class);
    }
}
