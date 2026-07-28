<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One role's decision to hide (or explicitly show) a single menu. Absence of a
 * row means visible — see the migration for why this exists alongside permissions.
 */
final class RoleMenuVisibility extends Model
{
    protected $table = 'role_menu_visibility';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Menu keys hidden for every one of the given roles. A user holding several
     * roles keeps a menu as long as one of their roles still shows it — the same
     * most-permissive rule permissions follow.
     *
     * @param  iterable<int, int>  $roleIds
     * @return array<int, string>
     */
    public static function keysHiddenForAll(iterable $roleIds): array
    {
        $ids = collect($roleIds)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return self::query()
            ->whereIn('role_id', $ids)
            ->where('is_visible', false)
            ->selectRaw('menu_key, COUNT(DISTINCT role_id) as hidden_for')
            ->groupBy('menu_key')
            ->having('hidden_for', '>=', $ids->count())
            ->pluck('menu_key')
            ->all();
    }

    /**
     * @param  Builder<RoleMenuVisibility>  $query
     * @return Builder<RoleMenuVisibility>
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
