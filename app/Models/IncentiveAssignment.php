<?php

namespace App\Models;

use App\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Which employees a scheme applies to, and from when. */
final class IncentiveAssignment extends Model
{
    use HasPublicId;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Assignments live on a date, ignoring the ones already ended. */
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

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(IncentiveScheme::class, 'incentive_scheme_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
