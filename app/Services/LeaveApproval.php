<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Carbon;

class LeaveApproval
{
    /**
     * Finalize a leave request as approved: flip the status, materialise the
     * "Cuti" attendance rows for the date range, and draw the days down from the
     * matching balance. Shared by the manual approval action and the automatic
     * approval a top approver's own request receives on submit.
     *
     * `$approverUserId` is a USER id, and `current_approver_id` holds an
     * EMPLOYEE id — that is the unit the mobile queue and the approval screen
     * both compare against. Writing the one into the other put a number with
     * the wrong meaning in the column: an approved leave then read as routed to
     * whichever employee happened to carry that id, and surfaced in a stranger's
     * approval history. It is translated here, and left alone when the approver
     * has no employee record, rather than overwritten with something false.
     */
    public static function finalize(LeaveRequest $leave, ?int $approverUserId = null): void
    {
        $approverEmployeeId = $approverUserId === null
            ? null
            : Employee::query()
                ->where('tenant_id', $leave->tenant_id)
                ->where('user_id', $approverUserId)
                ->value('id');

        $attributes = ['status' => 'approved'];

        if ($approverEmployeeId !== null) {
            $attributes['current_approver_id'] = $approverEmployeeId;
        }

        $leave->update($attributes);

        LeaveAttendanceMarker::mark($leave);

        // A sub-type has no balance of its own — the days come off the parent's.
        $leaveType = LeaveType::find($leave->leave_type_id);
        $quotaOwnerId = $leaveType?->quotaOwnerId() ?? $leave->leave_type_id;

        $balance = LeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $quotaOwnerId)
            ->where('year', Carbon::parse($leave->start_date)->year)
            ->first();

        if ($balance !== null) {
            $balance->update([
                'used' => $balance->used + $leave->total_days,
                'remaining' => max(0, $balance->remaining - $leave->total_days),
            ]);
        }
    }
}
