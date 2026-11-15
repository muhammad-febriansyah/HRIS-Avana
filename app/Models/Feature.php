<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Feature extends Model
{
    use SoftDeletes;

    /**
     * Features only the Super Admin may switch, from the Klien page. A tenant
     * admin never sees them on its own Fitur page: they carry a decision made
     * with the client (Alpa Otomatis docks pay), not a menu the company picks.
     *
     * @var list<string>
     */
    public const SUPER_ADMIN_ONLY = ['auto_alpa'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'permission_modules' => 'array',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_features')->withPivot('is_enabled')->withTimestamps();
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_features')->withPivot('is_enabled')->withTimestamps();
    }
}
