<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An incentive scheme: what is paid on top of salary, measured from what, and
 * as which payslip component.
 */
final class IncentiveScheme extends Model
{
    use HasPublicId, SoftDeletes;

    /** Present days in the period. */
    public const BASIS_ATTENDANCE = 'attendance';

    /** Latest performance review score. */
    public const BASIS_PERFORMANCE = 'performance';

    /** A figure HR enters for the employee for that period. */
    public const BASIS_TARGET = 'target';

    /** HR types the rupiah itself; the rules are not consulted. */
    public const BASIS_MANUAL = 'manual';

    public const BASES = [self::BASIS_ATTENDANCE, self::BASIS_PERFORMANCE, self::BASIS_TARGET, self::BASIS_MANUAL];

    public const ROUNDINGS = ['none', 'nearest', 'up', 'down'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'rounding_unit' => 'integer',
            'prorate_partial_period' => 'boolean',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Schemes in force on a date, ignoring the ones switched off. */
    public function scopeEffectiveOn(Builder $query, CarbonInterface|string|null $on): Builder
    {
        $date = $on instanceof CarbonInterface ? $on->toDateString() : (string) $on;

        return $query
            ->where('status', 'active')
            ->whereDate('effective_start_date', '<=', $date)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('effective_end_date')
                ->orWhereDate('effective_end_date', '>=', $date));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(IncentiveRule::class)->orderBy('sequence');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(IncentiveAssignment::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(IncentiveCalculation::class);
    }
}
