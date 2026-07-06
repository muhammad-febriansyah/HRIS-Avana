<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalaryMasterComponent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'included' => 'boolean',
            'is_prorate' => 'boolean',
            'is_overtime_base' => 'boolean',
            'is_kompensasi' => 'boolean',
        ];
    }

    public function salaryMaster(): BelongsTo
    {
        return $this->belongsTo(SalaryMaster::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
