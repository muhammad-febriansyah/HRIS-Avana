<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One bracket of a TER table: "in `category`, monthly bruto up to `income_max`
 * withholds `rate`". A null `income_max` is the open-ended top bracket.
 *
 * The rows are global — PPh 21 is national law, not tenant policy — and dated,
 * so entering a new PMK never rewrites what an earlier payroll run charged.
 */
final class Pph21TerRate extends Model
{
    use Auditable;

    protected $fillable = [
        'category',
        'income_min',
        'income_max',
        'rate',
        'effective_start_date',
        'effective_end_date',
        'source',
        'source_checksum',
        'change_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'income_min' => 'decimal:2',
            'income_max' => 'decimal:2',
            'rate' => 'decimal:6',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
        ];
    }

    /**
     * The rows in force on a date. A row with no start date has always applied;
     * one with no end date still does.
     */
    public function scopeEffectiveOn(Builder $query, string|CarbonInterface|null $date = null): Builder
    {
        $on = $date instanceof CarbonInterface ? $date->toDateString() : ($date ?? now()->toDateString());

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_start_date')->orWhereDate('effective_start_date', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('effective_end_date')->orWhereDate('effective_end_date', '>=', $on));
    }
}
