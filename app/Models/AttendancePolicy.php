<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant attendance verification policy. When a tenant has no row yet, the
 * model defaults (see {@see AttendancePolicy::resolve()}) apply so behaviour is
 * safe and backward-compatible out of the box.
 */
final class AttendancePolicy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'require_face_enrollment' => 'boolean',
            'require_liveness_challenge' => 'boolean',
            'block_mock_location' => 'boolean',
            'block_rooted' => 'boolean',
            'block_emulator' => 'boolean',
        ];
    }

    /**
     * Resolve the effective policy for a tenant, returning an unsaved default
     * instance (never null) when none is configured.
     */
    public static function resolve(int|string $tenantId): self
    {
        return self::query()->firstOrNew(
            ['tenant_id' => $tenantId],
            [
                'require_face_enrollment' => false,
                'require_liveness_challenge' => false,
                'face_enforcement' => 'block',
                'integrity_enforcement' => 'block',
                'block_mock_location' => true,
                'block_rooted' => true,
                'block_emulator' => true,
            ],
        );
    }

    public function blocksFace(): bool
    {
        return $this->face_enforcement === 'block';
    }

    public function blocksIntegrity(): bool
    {
        return $this->integrity_enforcement === 'block';
    }

    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
