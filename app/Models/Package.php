<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Package extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'feature_list' => 'array',
        ];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'package_features')->withPivot('is_enabled')->withTimestamps();
    }

    /**
     * Feature ids this tier entitles a tenant to. An empty set means the package
     * was never scoped, which the provisioner reads as "everything".
     *
     * @return array<int, int>
     */
    public function entitledFeatureIds(): array
    {
        return $this->features()
            ->wherePivot('is_enabled', true)
            ->pluck('features.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
