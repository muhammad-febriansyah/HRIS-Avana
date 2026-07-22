<?php

namespace App\Services;

use App\Models\OvertimeRequest;
use App\Models\Reimbursement;
use App\Models\WfhRequest;
use Illuminate\Support\Carbon;

/**
 * Approves a request the instant it is submitted by a top approver (a director,
 * who has no manager above them). Each method mirrors exactly what the matching
 * manual approve action does, so an auto-approved request is indistinguishable
 * from a hand-approved one. Leave has its own richer flow in LeaveApproval.
 */
class AutoApproval
{
    public static function overtime(OvertimeRequest $overtime): void
    {
        $overtime->update(['status' => 'approved']);
    }

    public static function wfh(WfhRequest $wfh): void
    {
        $wfh->update(['status' => 'approved']);
    }

    public static function reimbursement(Reimbursement $reimbursement, ?int $approverUserId): void
    {
        $reimbursement->update([
            'status' => 'approved',
            'approver_id' => $approverUserId,
            'approved_at' => Carbon::now(),
        ]);
    }
}
