<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

final class AttendancePolicy
{
    /**
     * Roles that always pass attendance authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Determine whether the user can view the attendance rekap list.
     *
     * @param  Attendance|class-string<Attendance>  $attendance
     */
    public function viewAny(User $user, Attendance|string $attendance = Attendance::class): bool
    {
        return $this->belongsToSameTenant($user, $attendance)
            && $this->hasAttendancePermission($user, 'attendance.view');
    }

    /**
     * Determine whether the user can approve attendance corrections.
     *
     * @param  Attendance|class-string<Attendance>  $attendance
     */
    public function approveCorrection(User $user, Attendance|string $attendance = Attendance::class): bool
    {
        return $this->belongsToSameTenant($user, $attendance)
            && $this->hasAttendancePermission($user, 'attendance.correction.approve');
    }

    /**
     * Determine whether the user can reject attendance corrections.
     *
     * @param  Attendance|class-string<Attendance>  $attendance
     */
    public function rejectCorrection(User $user, Attendance|string $attendance = Attendance::class): bool
    {
        return $this->belongsToSameTenant($user, $attendance)
            && $this->hasAttendancePermission($user, 'attendance.correction.approve');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     *
     * @param  Attendance|class-string<Attendance>  $attendance
     */
    private function belongsToSameTenant(User $user, Attendance|string $attendance): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (! $attendance instanceof Attendance) {
            return true;
        }

        return (int) $user->tenant_id === (int) $attendance->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasAttendancePermission(User $user, string $permission): bool
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
