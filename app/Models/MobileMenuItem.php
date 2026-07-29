<?php

namespace App\Models;

use App\Support\MobileMenu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tile of the Flutter app's Menu Cepat, for one tenant.
 *
 * @see MobileMenu for the set the app ships with.
 */
class MobileMenuItem extends Model
{
    /**
     * Prefix used when a tile's per-role visibility is written to
     * `role_menu_visibility`, which is shared with the web sidebar.
     *
     * The two never collide because no web menu key starts with this, and the
     * question both tables answer is identical — "does this role see this
     * menu?" — so a second table would only duplicate the logic.
     */
    public const VISIBILITY_PREFIX = 'mobile.';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
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

    /** The key this tile's visibility rows are stored under. */
    public function visibilityKey(): string
    {
        return self::VISIBILITY_PREFIX.$this->key;
    }
}
