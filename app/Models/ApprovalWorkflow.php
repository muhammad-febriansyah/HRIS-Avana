<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ApprovalWorkflow extends Model
{
    use SoftDeletes;

    /**
     * `active_scope` is a stored generated column on MySQL — it exists only to
     * carry the "one workflow per module per department" unique index, and the
     * database refuses any write to it.
     *
     * @var array<int, string>
     */
    protected $guarded = ['active_scope'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'conditions' => 'array',
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

    /** The division this flow is limited to; null = every division (default). */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<ApprovalStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    /** @return HasMany<ApprovalRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }
}
