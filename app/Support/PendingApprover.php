<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\AttendanceCorrection;
use App\Models\DataChangeRequest;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\User;
use App\Models\WfhRequest;
use App\Services\ApprovalEngine;

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
        Reimbursement::class,
        DutyTravel::class,
        DataChangeRequest::class,
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
        $employee = Employee::forTenant($user->tenant_id)
            ->where('user_id', $user->id)
            ->first();

        if ($employee === null) {
            return false;
        }

        foreach (self::MODELS as $model) {
            $waiting = $model::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'pending')
                ->where('current_approver_id', $employee->getKey())
                ->exists();

            if ($waiting) {
                return true;
            }
        }

        // A workflow step aimed at a group (role / department / position) names
        // no single approver, so neither cursor points at anyone: the step
        // itself decides who may act, and every holder qualifies.
        foreach (self::MODELS as $model) {
            if (ApprovalEngine::pendingApprovableIdsFor($model, $employee) !== []) {
                return true;
            }
        }

        return false;
    }
}
