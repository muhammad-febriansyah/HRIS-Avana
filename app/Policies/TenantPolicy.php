<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    /**
     * The platform-level role allowed to manage every tenant. Only the
     * super_admin holds the tenant.* permissions, so client management is
     * restricted to it (a tenant-scoped admin_tenant_hr is denied).
     */
    private const SUPER_ADMIN_ROLE = 'super_admin';

    /**
     * Determine whether the user can view the tenant list.
     */
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can update the tenant (and its features).
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can archive (soft delete) the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Only a user carrying the super_admin role passes platform authorization.
     */
    private function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->roles->contains('code', self::SUPER_ADMIN_ROLE);
    }
}
