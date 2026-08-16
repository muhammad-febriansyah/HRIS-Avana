<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\User;
use App\Services\LeaveBalanceProvisioner;

/**
 * Keeps an employee's login account in step with their "Status Karyawan":
 * marking an employee inactive blocks their app login and revokes any active
 * sessions; marking them active again restores access. Also opens the leave
 * balances of a new hire so they do not have to wait for the next yearly run.
 */
final class EmployeeObserver
{
    /**
     * Give a new active employee this year's leave balances straight away —
     * without a row, their Cuti screen reads "Belum ada data saldo" and an
     * approved leave deducts nothing.
     */
    public function created(Employee $employee): void
    {
        if ($employee->status === 'inactive') {
            return;
        }

        LeaveBalanceProvisioner::forEmployee($employee, (int) now()->year);
    }

    /**
     * React whenever the status — or the moment a login is first linked —
     * changes, and reconcile the login account's access.
     */
    public function saved(Employee $employee): void
    {
        if (! $employee->wasRecentlyCreated
            && ! $employee->wasChanged('status')
            && ! $employee->wasChanged('user_id')) {
            return;
        }

        $this->syncLoginAccess($employee);
    }

    /**
     * Block the login when the employee is inactive, restore it otherwise.
     * Blocking bumps the token version so existing mobile tokens are rejected;
     * restoring leaves it untouched.
     */
    private function syncLoginAccess(Employee $employee): void
    {
        if ($employee->user_id === null) {
            return;
        }

        $user = User::find($employee->user_id);

        if ($user === null) {
            return;
        }

        $shouldBlock = $employee->status === 'inactive';
        $targetStatus = $shouldBlock ? 'inactive' : 'active';

        if ($user->status === $targetStatus) {
            return;
        }

        $user->forceFill([
            'status' => $targetStatus,
            'token_version' => $shouldBlock ? (int) $user->token_version + 1 : $user->token_version,
        ])->save();
    }
}
