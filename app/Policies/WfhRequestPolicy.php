<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WfhRequest;

final class WfhRequestPolicy
{
    /**
     * Roles that always pass WFH authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Determine whether the user can view the WFH request list.
     *
     * @param  WfhRequest|class-string<WfhRequest>  $wfh
     */
    public function viewAny(User $user, WfhRequest|string $wfh = WfhRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $wfh)
            && $this->hasWfhPermission($user, 'wfh.approve');
    }

    /**
     * Determine whether the user can create WFH requests.
     *
     * @param  WfhRequest|class-string<WfhRequest>  $wfh
     */
    public function create(User $user, WfhRequest|string $wfh = WfhRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $wfh)
            && $this->hasWfhPermission($user, 'wfh.approve');
    }

    /**
     * Determine whether the user can approve the WFH request.
     *
     * @param  WfhRequest|class-string<WfhRequest>  $wfh
     */
    public function approve(User $user, WfhRequest|string $wfh): bool
    {
        return $this->belongsToSameTenant($user, $wfh)
            && $this->hasWfhPermission($user, 'wfh.approve');
    }

    /**
     * Determine whether the user can reject the WFH request.
     *
     * @param  WfhRequest|class-string<WfhRequest>  $wfh
     */
    public function reject(User $user, WfhRequest|string $wfh): bool
    {
        return $this->belongsToSameTenant($user, $wfh)
            && $this->hasWfhPermission($user, 'wfh.approve');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     *
     * @param  WfhRequest|class-string<WfhRequest>  $wfh
     */
    private function belongsToSameTenant(User $user, WfhRequest|string $wfh): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (! $wfh instanceof WfhRequest) {
            return true;
        }

        return (int) $user->tenant_id === (int) $wfh->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasWfhPermission(User $user, string $permission): bool
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
