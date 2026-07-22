<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;

class LeaveApproval
{
    /**
     * Finalize a leave request as approved: flip the status, materialise the
     * "Cuti" attendance rows for the date range, and draw the days down from the
     * matching balance. Shared by the manual approval action and the automatic
     * approval a top approver's own request receives on submit.
     */
    public static function finalize(LeaveRequest $leave, ?int $approverId = null): void
    {
        $leave->update([
            'status' => 'approved',
            'current_approver_id' => $approverId,
        ]);

        LeaveAttendanceMarker::mark($leave);

        $balance = LeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', $leave->start_date->year)
            ->first();

        if ($balance !== null) {
            $balance->update([
                'used' => $balance->used + $leave->total_days,
                'remaining' => max(0, $balance->remaining - $leave->total_days),
            ]);
        }
    }
}
