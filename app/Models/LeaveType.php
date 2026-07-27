<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A leave type, optionally branched one level deep.
 *
 * A root type (`parent_id === null`) owns the yearly quota and the employee
 * balance. A sub-type hangs off a root, carries no quota of its own, and draws
 * from the parent's balance; `sub_limit` optionally caps how many of the
 * parent's days it may take. Sub-types leave `allow_negative` /
 * `requires_attachment` null to inherit the parent's setting.
 */
final class LeaveType extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_quota' => 'integer',
            'sub_limit' => 'integer',
            'allow_negative' => 'boolean',
            'requires_attachment' => 'boolean',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Only the quota-owning types, i.e. those without a parent.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * True when this type draws from another type's quota.
     */
    public function isSub(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * The type that owns the quota and the employee balance: the parent for a
     * sub-type, otherwise the type itself.
     */
    public function quotaOwner(): self
    {
        return $this->isSub() ? ($this->parent ?? $this) : $this;
    }

    /**
     * Id of the quota owner. Cheaper than {@see self::quotaOwner()} when only
     * the key is needed, since it never loads the parent.
     */
    public function quotaOwnerId(): int
    {
        return (int) ($this->parent_id ?? $this->getKey());
    }

    /**
     * Yearly quota to draw from — always the owner's, never the sub-type's.
     */
    public function effectiveQuota(): int
    {
        return (int) $this->quotaOwner()->default_quota;
    }

    /**
     * Whether the balance may go negative, falling back to the parent when a
     * sub-type leaves the toggle unset.
     */
    public function effectiveAllowNegative(): bool
    {
        if ($this->allow_negative !== null) {
            return (bool) $this->allow_negative;
        }

        // Only a sub-type leaves the toggle unset, and it always has a parent.
        return $this->isSub() && (bool) $this->parent->allow_negative;
    }

    /**
     * Whether a supporting document is required, falling back to the parent
     * when a sub-type leaves the toggle unset.
     */
    public function effectiveRequiresAttachment(): bool
    {
        if ($this->requires_attachment !== null) {
            return (bool) $this->requires_attachment;
        }

        // Only a sub-type leaves the toggle unset, and it always has a parent.
        return $this->isSub() && (bool) $this->parent->requires_attachment;
    }

    /**
     * A branched root is a container, not a choice: the employee must pick one
     * of its sub-types so the days land in the right bucket.
     */
    public function isSelectable(): bool
    {
        return ! ($this->children_count ?? $this->children()->count());
    }

    /**
     * The tenant's active leave types shaped for a picker: roots in order, each
     * with its selectable sub-types nested underneath.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function selectableTree(int|string $tenantId): array
    {
        return self::forTenant($tenantId)
            ->roots()
            ->where('status', 'active')
            ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (self $root): array => [
                'id' => $root->id,
                'code' => $root->code,
                'name' => $root->name,
                'default_quota' => (int) $root->default_quota,
                'requires_attachment' => (bool) $root->requires_attachment,
                // A root with children is a group header, not a choice.
                'selectable' => $root->children->isEmpty(),
                'children' => $root->children->map(fn (self $child): array => [
                    'id' => $child->id,
                    'code' => $child->code,
                    'name' => $child->name,
                    'sub_limit' => $child->sub_limit,
                    'requires_attachment' => $child->effectiveRequiresAttachment(),
                    'selectable' => true,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
