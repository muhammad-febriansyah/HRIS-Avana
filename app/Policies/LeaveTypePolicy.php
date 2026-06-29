<?php

namespace App\Policies;

use App\Models\LeaveType;
use App\Models\User;

final class LeaveTypePolicy
{
    /**
     * Roles that always pass leave type authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Determine whether the user can view the leave type list.
     *
     * @param  LeaveType|class-string<LeaveType>  $leaveType
     */
    public function viewAny(User $user, LeaveType|string $leaveType = LeaveType::class): bool
    {
        return $this->belongsToSameTenant($user, $leaveType)
            && $this->hasLeavePermission($user, 'leave.view');
    }

    /**
     * Determine whether the user can create leave types.
     *
     * @param  LeaveType|class-string<LeaveType>  $leaveType
     */
    public function create(User $user, LeaveType|string $leaveType = LeaveType::class): bool
    {
        return $this->belongsToSameTenant($user, $leaveType)
            && $this->hasLeavePermission($user, 'leave.manage');
    }

    /**
     * Determine whether the user can update the leave type.
     *
     * @param  LeaveType|class-string<LeaveType>  $leaveType
     */
    public function update(User $user, LeaveType|string $leaveType): bool
    {
        return $this->belongsToSameTenant($user, $leaveType)
            && $this->hasLeavePermission($user, 'leave.manage');
    }

    /**
     * Determine whether the user can delete the leave type.
     *
     * @param  LeaveType|class-string<LeaveType>  $leaveType
     */
    public function delete(User $user, LeaveType|string $leaveType): bool
    {
        return $this->belongsToSameTenant($user, $leaveType)
            && $this->hasLeavePermission($user, 'leave.manage');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     *
     * @param  LeaveType|class-string<LeaveType>  $leaveType
     */
    private function belongsToSameTenant(User $user, LeaveType|string $leaveType): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (! $leaveType instanceof LeaveType) {
            return true;
        }

        return (int) $user->tenant_id === (int) $leaveType->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasLeavePermission(User $user, string $permission): bool
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
