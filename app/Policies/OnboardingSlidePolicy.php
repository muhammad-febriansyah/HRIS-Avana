<?php

namespace App\Policies;

use App\Models\OnboardingSlide;
use App\Models\User;

/**
 * Onboarding slides are a platform-level (super admin) concern — the mobile
 * app intro is shared across all tenants. Mirrors {@see WebsiteSettingPolicy}.
 */
final class OnboardingSlidePolicy
{
    private const SUPER_ADMIN_ROLE = 'super_admin';

    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, OnboardingSlide $onboardingSlide): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, OnboardingSlide $onboardingSlide): bool
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
