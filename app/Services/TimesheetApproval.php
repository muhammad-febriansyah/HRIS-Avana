<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Support\Carbon;

/**
 * The two ends of a timesheet entry's approval, in one place so an entry
 * decided on a workflow's last step, from the web approval centre, from the
 * MSS queue on a phone, or auto-approved because a director filed it, all land
 * in exactly the same state.
 *
 * Approval is also when the entry is priced: the rates are read once, at the
 * moment the hours become real, and frozen onto the row. A later raise or a
 * re-priced project therefore never rewrites a report that has already been
 * shown to a client.
 */
final class TimesheetApproval
{
    public static function finalize(Timesheet $timesheet, ?int $actorUserId): void
    {
        $timesheet->update([
            'status' => Timesheet::STATUS_APPROVED,
            'approved_by' => $actorUserId,
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
            'current_approver_id' => null,
            ...self::pricing($timesheet),
        ]);
    }

    public static function reject(Timesheet $timesheet, ?int $actorUserId, ?string $reason = null): void
    {
        $timesheet->update([
            'status' => Timesheet::STATUS_REJECTED,
            'approved_by' => $actorUserId,
            'approved_at' => null,
            'rejection_reason' => $reason,
            'current_approver_id' => null,
        ]);
    }

    /**
     * The costing columns to freeze onto the entry, or an empty set when the
     * employee or project has since been deleted.
     *
     * @return array<string, mixed>
     */
    private static function pricing(Timesheet $timesheet): array
    {
        $employee = $timesheet->employee ?? Employee::find($timesheet->employee_id);
        $project = $timesheet->project ?? Project::find($timesheet->project_id);

        if ($employee === null || $project === null) {
            return [];
        }

        return TimesheetCosting::priceFor(
            $employee,
            $project,
            (float) $timesheet->hours,
            (bool) $timesheet->is_billable,
        );
    }
}
