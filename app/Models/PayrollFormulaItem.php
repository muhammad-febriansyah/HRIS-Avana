<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PayrollFormulaItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:4',
            'prorate' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(PayrollFormula::class, 'payroll_formula_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
