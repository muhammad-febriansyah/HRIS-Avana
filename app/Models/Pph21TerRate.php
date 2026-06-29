<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Pph21TerRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'income_min' => 'decimal:2',
            'income_max' => 'decimal:2',
            'rate' => 'decimal:4',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
