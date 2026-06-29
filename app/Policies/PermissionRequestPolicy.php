<?php

namespace App\Policies;

use App\Models\PermissionRequest;
use App\Models\User;

final class PermissionRequestPolicy
{
    /**
     * Roles that always pass permission/izin authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Determine whether the user can view the permission (izin) request list.
     *
     * @param  PermissionRequest|class-string<PermissionRequest>  $permissionRequest
     */
    public function viewAny(User $user, PermissionRequest|string $permissionRequest = PermissionRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $permissionRequest)
            && $this->hasPermission($user, 'leave.view');
    }

    /**
     * Determine whether the user can create permission (izin) requests.
     *
     * @param  PermissionRequest|class-string<PermissionRequest>  $permissionRequest
     */
    public function create(User $user, PermissionRequest|string $permissionRequest = PermissionRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $permissionRequest)
            && $this->hasPermission($user, 'leave.manage');
    }

    /**
     * Determine whether the user can approve the permission (izin) request.
     *
     * @param  PermissionRequest|class-string<PermissionRequest>  $permissionRequest
     */
    public function approve(User $user, PermissionRequest|string $permissionRequest): bool
    {
        return $this->belongsToSameTenant($user, $permissionRequest)
            && $this->hasPermission($user, 'leave.approve');
    }

    /**
     * Determine whether the user can reject the permission (izin) request.
     *
     * @param  PermissionRequest|class-string<PermissionRequest>  $permissionRequest
     */
    public function reject(User $user, PermissionRequest|string $permissionRequest): bool
    {
        return $this->belongsToSameTenant($user, $permissionRequest)
            && $this->hasPermission($user, 'leave.approve');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     *
     * @param  PermissionRequest|class-string<PermissionRequest>  $permissionRequest
     */
    private function belongsToSameTenant(User $user, PermissionRequest|string $permissionRequest): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (! $permissionRequest instanceof PermissionRequest) {
            return true;
        }

        return (int) $user->tenant_id === (int) $permissionRequest->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasPermission(User $user, string $permission): bool
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
