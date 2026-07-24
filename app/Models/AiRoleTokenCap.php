<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-role monthly AI token cap within a tenant. A null `monthly_cap` (or no row
 * for the role) means the role inherits the tenant default; null default means
 * unlimited. When a user holds several roles the most permissive cap applies.
 */
final class AiRoleTokenCap extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monthly_cap' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
