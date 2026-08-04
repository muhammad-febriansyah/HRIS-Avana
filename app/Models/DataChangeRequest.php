<?php

namespace App\Models;

use App\Support\DataChangeFields;
use Database\Factories\DataChangeRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's request to have their own personal data corrected. `changes`
 * holds `{field: {old, new}}`; approving it writes each `new` onto the record
 * through {@see DataChangeFields}.
 */
final class DataChangeRequest extends Model
{
    /** @use HasFactory<DataChangeRequestFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'decided_at' => 'datetime',
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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'current_approver_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
