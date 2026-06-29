<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BpjsRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:4',
            'company_rate' => 'decimal:4',
            'max_wage' => 'decimal:2',
            'min_wage' => 'decimal:2',
            'effective_start_date' => 'date',
            'effective_end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(BpjsProgram::class, 'program_id');
    }
}
