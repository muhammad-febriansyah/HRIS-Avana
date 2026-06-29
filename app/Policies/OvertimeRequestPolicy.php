<?php

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;

final class OvertimeRequestPolicy
{
    /**
     * Roles that always pass overtime authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Determine whether the user can view the overtime request list.
     *
     * @param  OvertimeRequest|class-string<OvertimeRequest>  $overtime
     */
    public function viewAny(User $user, OvertimeRequest|string $overtime = OvertimeRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $overtime)
            && $this->hasOvertimePermission($user, 'overtime.view');
    }

    /**
     * Determine whether the user can create overtime requests.
     *
     * @param  OvertimeRequest|class-string<OvertimeRequest>  $overtime
     */
    public function create(User $user, OvertimeRequest|string $overtime = OvertimeRequest::class): bool
    {
        return $this->belongsToSameTenant($user, $overtime)
            && $this->hasOvertimePermission($user, 'overtime.view');
    }

    /**
     * Determine whether the user can approve the overtime request.
     *
     * @param  OvertimeRequest|class-string<OvertimeRequest>  $overtime
     */
    public function approve(User $user, OvertimeRequest|string $overtime): bool
    {
        return $this->belongsToSameTenant($user, $overtime)
            && $this->hasOvertimePermission($user, 'overtime.approve');
    }

    /**
     * Determine whether the user can reject the overtime request.
     *
     * @param  OvertimeRequest|class-string<OvertimeRequest>  $overtime
     */
    public function reject(User $user, OvertimeRequest|string $overtime): bool
    {
        return $this->belongsToSameTenant($user, $overtime)
            && $this->hasOvertimePermission($user, 'overtime.approve');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     *
     * @param  OvertimeRequest|class-string<OvertimeRequest>  $overtime
     */
    private function belongsToSameTenant(User $user, OvertimeRequest|string $overtime): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (! $overtime instanceof OvertimeRequest) {
            return true;
        }

        return (int) $user->tenant_id === (int) $overtime->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasOvertimePermission(User $user, string $permission): bool
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
