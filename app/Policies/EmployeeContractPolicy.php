<?php

namespace App\Policies;

use App\Models\EmployeeContract;
use App\Models\User;

final class EmployeeContractPolicy
{
    /**
     * Roles that always pass contract authorization within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Determine whether the user can view the contract list.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasEmployeePermission($user, 'employee.view');
    }

    /**
     * Determine whether the user can view the contract.
     */
    public function view(User $user, EmployeeContract $contract): bool
    {
        return $this->belongsToSameTenant($user, $contract)
            && $this->hasEmployeePermission($user, 'employee.view');
    }

    /**
     * Determine whether the user can create contracts.
     */
    public function create(User $user): bool
    {
        return $this->hasEmployeePermission($user, 'employee.create');
    }

    /**
     * Determine whether the user can update the contract.
     */
    public function update(User $user, EmployeeContract $contract): bool
    {
        return $this->belongsToSameTenant($user, $contract)
            && $this->hasEmployeePermission($user, 'employee.update');
    }

    /**
     * Determine whether the user can delete the contract.
     */
    public function delete(User $user, EmployeeContract $contract): bool
    {
        return $this->belongsToSameTenant($user, $contract)
            && $this->hasEmployeePermission($user, 'employee.archive');
    }

    /**
     * The user and contract must belong to the same tenant.
     */
    private function belongsToSameTenant(User $user, EmployeeContract $contract): bool
    {
        return $user->tenant_id !== null
            && (int) $user->tenant_id === (int) $contract->tenant_id;
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
