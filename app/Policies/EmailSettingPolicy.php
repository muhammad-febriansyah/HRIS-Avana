<?php

namespace App\Policies;

use App\Models\User;

/**
 * Email settings are managed by the platform super admin (platform default) and
 * by each tenant's HR admin (their own override), mirroring the privileged-role
 * gating used across the other Avana settings screens.
 */
final class EmailSettingPolicy
{
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Determine whether the user can view the email settings screen.
     */
    public function view(User $user): bool
    {
        return $this->isPrivileged($user);
    }

    /**
     * Determine whether the user can update the email settings.
     */
    public function update(User $user): bool
    {
        return $this->isPrivileged($user);
    }

    /**
     * A super admin or a tenant HR admin passes.
     */
    private function isPrivileged(User $user): bool
    {
        $user->loadMissing('roles');

        return $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();
    }
}
