<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TenantAddon extends Model
{
    public const MULTI_COMPANY = 'multi_company';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'company_limit' => 'integer',
        ];
    }

    public function tenantGroup(): BelongsTo
    {
        return $this->belongsTo(TenantGroup::class);
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
