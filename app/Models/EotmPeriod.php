<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One month of Employee of the Month voting.
 *
 * `draft` is being prepared, `open` accepts votes, `closed` is final — the
 * winner and their vote count are stamped onto the row at close, so the result
 * no longer depends on the votes still existing.
 */
final class EotmPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'winner_votes' => 'integer',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<EotmVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(EotmVote::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'winner_employee_id');
    }

    /**
     * "Juni 2026" — the period label shown to employees.
     */
    public function label(): string
    {
        return Carbon::createFromFormat('Y-m', $this->period)
            ->locale('id')
            ->translatedFormat('F Y');
    }
}
