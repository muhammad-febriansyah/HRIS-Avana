<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Which TER table a PTKP status reads from — "K/3 uses Kategori C".
 *
 * Kept beside the brackets rather than in code because PMK 168/2023 sets the
 * mapping too, and a later regulation can move a status between categories
 * without touching a single rate.
 */
final class Pph21TerCategory extends Model
{
    use Auditable;

    protected $fillable = [
        'ptkp_status',
        'category',
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
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
        ];
    }

    /**
     * The rows in force on a date.
     */
    public function scopeEffectiveOn(Builder $query, string|CarbonInterface|null $date = null): Builder
    {
        $on = $date instanceof CarbonInterface ? $date->toDateString() : ($date ?? now()->toDateString());

        return $query
            ->where(fn (Builder $q) => $q->whereNull('effective_start_date')->orWhereDate('effective_start_date', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('effective_end_date')->orWhereDate('effective_end_date', '>=', $on));
    }
}
