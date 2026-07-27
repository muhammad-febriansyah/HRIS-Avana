<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One employee's vote in a period. The (period, voter) unique index enforces
 * one vote each; changing your mind updates this row rather than adding one.
 */
final class EotmVote extends Model
{
    protected $guarded = [];

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(EotmPeriod::class, 'eotm_period_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'voter_employee_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function nominee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'nominee_employee_id');
    }

    /** @return BelongsTo<EotmCoreValue, $this> */
    public function coreValue(): BelongsTo
    {
        return $this->belongsTo(EotmCoreValue::class, 'eotm_core_value_id');
    }
}
