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
    /** Clock in only at the employee's own work location / branch. */
    public const SCOPE_ASSIGNED = 'assigned';

    /** Clock in at any active work location in the tenant; radius still applies. */
    public const SCOPE_ANY_BRANCH = 'any_branch';

    /** Work from anywhere: the geofence radius is not enforced. */
    public const SCOPE_ANYWHERE = 'anywhere';

    /**
     * Every selectable scope, loosest last.
     *
     * @var array<int, string>
     */
    public const SCOPES = [self::SCOPE_ASSIGNED, self::SCOPE_ANY_BRANCH, self::SCOPE_ANYWHERE];

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
                'attendance_scope' => self::SCOPE_ASSIGNED,
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
