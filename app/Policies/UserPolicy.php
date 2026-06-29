<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    /**
     * Roles that always pass user-management authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Determine whether the user can view the user list.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasUserPermission($user, 'user.view');
    }

    /**
     * Determine whether the user can view the target user.
     */
    public function view(User $user, User $target): bool
    {
        return $this->belongsToSameTenant($user, $target)
            && $this->hasUserPermission($user, 'user.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $this->hasUserPermission($user, 'user.create');
    }

    /**
     * Determine whether the user can update the target user.
     */
    public function update(User $user, User $target): bool
    {
        return $this->belongsToSameTenant($user, $target)
            && $this->hasUserPermission($user, 'user.update');
    }

    /**
     * Determine whether the user can delete (disable) the target user.
     */
    public function delete(User $user, User $target): bool
    {
        return $this->belongsToSameTenant($user, $target)
            && $this->hasUserPermission($user, 'user.disable');
    }

    /**
     * The acting user and the target user must belong to the same tenant.
     */
    private function belongsToSameTenant(User $user, User $target): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $target->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasUserPermission(User $user, string $permission): bool
    {
        $user->loadMissing('roles.permissions');

        if ($user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty()) {
            return true;
        }

        return $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains($permission);
    }
}
