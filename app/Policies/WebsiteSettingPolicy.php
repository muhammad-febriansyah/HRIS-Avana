<?php

namespace App\Policies;

use App\Models\User;

/**
 * Website settings are a platform-level (super admin) concern, mirroring the
 * tenant-management gating in {@see TenantPolicy}.
 */
final class WebsiteSettingPolicy
{
    private const SUPER_ADMIN_ROLE = 'super_admin';

    /**
     * Determine whether the user can view the website settings screen.
     */
    public function view(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Determine whether the user can update the website settings.
     */
    public function update(User $user): bool
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
