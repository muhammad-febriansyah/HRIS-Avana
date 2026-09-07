<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PayrollPeriod extends Model
{
    /**
     * A THR period: one bonus payment stamped across a whole year, not an
     * attendance window. It reads no attendance, so it neither competes with a
     * regular period for the days it spans nor belongs in the salary-period
     * arithmetic that walks month by month.
     */
    public const TYPE_THR = 'thr';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'pay_date' => 'date',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Every period except THR — the ones a payday cycle actually produces.
     *
     * This used to be spelled `where('code', 'not like', 'THR-%')` in a dozen
     * places, which made the rule a naming convention: a THR period whose code
     * was typed differently silently became a regular period everywhere.
     */
    public function scopeRegular(Builder $query): Builder
    {
        return $query->where('type', '!=', self::TYPE_THR);
    }

    public function scopeThr(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_THR);
    }

    public function isThr(): bool
    {
        return $this->getAttribute('type') === self::TYPE_THR;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
