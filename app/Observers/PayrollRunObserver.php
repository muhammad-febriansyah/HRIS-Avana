<?php

namespace App\Observers;

use App\Models\PayrollRun;
use App\Support\Notifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Notifies each employee that their payslip is ready the moment a payroll run
 * is finalized (status → locked). Re-locking does not re-notify.
 */
class PayrollRunObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(PayrollRun $run): void
    {
        if (! $run->wasChanged('status')) {
            return;
        }

        if ($run->status !== 'locked') {
            return;
        }

        Notifier::payrollLocked($run);
    }
}
