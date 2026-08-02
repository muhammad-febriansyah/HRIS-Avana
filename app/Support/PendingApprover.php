<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Models\WfhRequest;

/**
 * Whether something is currently waiting on this person to decide it.
 *
 * A workflow names its approvers by relationship — "atasan langsung" is
 * whoever the employee reports to, not whoever HR granted an approval module
 * to. The approval screen is gated on those modules, so a named approver who
 * holds none of them is refused the very screen their own step routes to, and
 * the request sits there until an admin overrides it.
 *
 * Being the approver a pending request is waiting on is licence enough, and
 * only for as long as that is true.
 */
final class PendingApprover
{
    /**
     * Request models whose `current_approver_id` names an employee.
     *
     * @var array<int, class-string>
     */
    private const MODELS = [
        LeaveRequest::class,
        OvertimeRequest::class,
        PermissionRequest::class,
        WfhRequest::class,
        AttendanceCorrection::class,
    ];

    public static function awaits(User $user): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        // The engine's own cursor, which holds a user id.
        $onWorkflow = ApprovalRequest::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'pending')
            ->where('current_approver_id', $user->id)
            ->exists();

        if ($onWorkflow) {
            return true;
        }

        // The request models hold an EMPLOYEE id, which is what the mobile
        // queue compares against.
        $employeeId = Employee::forTenant($user->tenant_id)
            ->where('user_id', $user->id)
            ->value('id');

        if ($employeeId === null) {
            return false;
        }

        foreach (self::MODELS as $model) {
            $waiting = $model::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'pending')
                ->where('current_approver_id', $employeeId)
                ->exists();

            if ($waiting) {
                return true;
            }
        }

        return false;
    }
}
