<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Tenant extends Model
{
    use Auditable, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'enforce_payroll_segregation' => 'boolean',
            'theme' => 'array',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }

    /**
     * Whether a named feature is switched on for this company.
     *
     * The sidebar and the access middleware resolve the whole enabled set at
     * once; this is for the places that only care about one — an AI tool that
     * should not be offered, an endpoint that should not answer.
     */
    public function hasFeature(string $code): bool
    {
        return $this->features()
            ->where('is_enabled', true)
            ->whereIn('feature_id', Feature::query()->where('code', $code)->select('id'))
            ->exists();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
