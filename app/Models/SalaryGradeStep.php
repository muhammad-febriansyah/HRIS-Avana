<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalaryGradeStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'masa_kerja' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function salaryGrade(): BelongsTo
    {
        return $this->belongsTo(SalaryGrade::class);
    }
}
