<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\PositionPayrollComponent;
use App\Models\User;

final class PayrollPolicy
{
    /**
     * Roles that always pass payroll authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Determine whether the user can view payroll data.
     */
    public function viewAny(User $user, PayrollPeriod|PositionPayrollComponent|string $subject = PayrollPeriod::class): bool
    {
        return $this->belongsToSameTenant($user, $subject)
            && $this->hasPayrollPermission($user, 'payroll.view');
    }

    /**
     * Determine whether the user can run/recalculate payroll.
     */
    public function run(User $user, PayrollPeriod|PositionPayrollComponent|string $subject = PayrollPeriod::class): bool
    {
        return $this->belongsToSameTenant($user, $subject)
            && $this->hasPayrollPermission($user, 'payroll.run');
    }

    /**
     * Determine whether the user can manage payroll configuration.
     */
    public function manage(User $user, PayrollPeriod|PositionPayrollComponent|string $subject = PositionPayrollComponent::class): bool
    {
        return $this->belongsToSameTenant($user, $subject)
            && $this->hasPayrollPermission($user, 'payroll.manage');
    }

    /**
     * The user must have a tenant and, for a concrete model, share its tenant.
     */
    private function belongsToSameTenant(User $user, PayrollPeriod|PositionPayrollComponent|string $subject): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        if (is_string($subject)) {
            return true;
        }

        return (int) $user->tenant_id === (int) $subject->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasPayrollPermission(User $user, string $permission): bool
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
