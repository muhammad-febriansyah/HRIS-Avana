<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TenantGroup extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }
}
