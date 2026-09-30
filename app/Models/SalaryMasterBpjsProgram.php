<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalaryMasterBpjsProgram extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'included' => 'boolean',
        ];
    }

    public function salaryMaster(): BelongsTo
    {
        return $this->belongsTo(SalaryMaster::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(BpjsProgram::class, 'bpjs_program_id');
    }
}
