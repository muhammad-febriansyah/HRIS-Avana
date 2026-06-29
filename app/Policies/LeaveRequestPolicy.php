<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

final class LeaveRequestPolicy
{
    /**
     * Roles that always pass leave authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Determine whether the user can view the leave request list.
     *
     * @param  LeaveRequest|class-string<LeaveRequest>  $leave
     */
    public function viewAny(User $user, LeaveRequest|string $leave = LeaveRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $leave)
            && $this->hasLeavePermission($user, 'leave.view');
    }

    /**
     * Determine whether the user can create leave requests.
     *
     * @param  LeaveRequest|class-string<LeaveRequest>  $leave
     */
    public function create(User $user, LeaveRequest|string $leave = LeaveRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $leave)
            && $this->hasLeavePermission($user, 'leave.manage');
    }

    /**
     * Determine whether the user can approve the leave request.
     *
     * @param  LeaveRequest|class-string<LeaveRequest>  $leave
     */
    public function approve(User $user, LeaveRequest|string $leave): bool
    {
        return $this->belongsToSameTenant($user, $leave)
            && $this->hasLeavePermission($user, 'leave.approve');
    }

    /**
     * Determine whether the user can reject the leave request.
     *
     * @param  LeaveRequest|class-string<LeaveRequest>  $leave
     */
    public function reject(User $user, LeaveRequest|string $leave): bool
    {
        return $this->belongsToSameTenant($user, $leave)
            && $this->hasLeavePermission($user, 'leave.approve');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     *
     * @param  LeaveRequest|class-string<LeaveRequest>  $leave
     */
    private function belongsToSameTenant(User $user, LeaveRequest|string $leave): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (! $leave instanceof LeaveRequest) {
            return true;
        }

        return (int) $user->tenant_id === (int) $leave->tenant_id;
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
