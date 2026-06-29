<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

final class EmployeePolicy
{
    /**
     * Roles that always pass employee authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Determine whether the user can view the employee list.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasEmployeePermission($user, 'employee.view');
    }

    /**
     * Determine whether the user can view the employee.
     */
    public function view(User $user, Employee $employee): bool
    {
        return $this->belongsToSameTenant($user, $employee)
            && $this->hasEmployeePermission($user, 'employee.view');
    }

    /**
     * Determine whether the user can create employees.
     */
    public function create(User $user): bool
    {
        return $this->hasEmployeePermission($user, 'employee.create');
    }

    /**
     * Determine whether the user can update the employee.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $this->belongsToSameTenant($user, $employee)
            && $this->hasEmployeePermission($user, 'employee.update');
    }

    /**
     * Determine whether the user can archive (soft delete) the employee.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $this->belongsToSameTenant($user, $employee)
            && $this->hasEmployeePermission($user, 'employee.archive');
    }

    /**
     * The user and employee must belong to the same tenant.
     */
    private function belongsToSameTenant(User $user, Employee $employee): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $employee->tenant_id;
    }

    /**
     * Privileged roles bypass the permission check; otherwise require the code.
     */
    private function hasEmployeePermission(User $user, string $permission): bool
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
