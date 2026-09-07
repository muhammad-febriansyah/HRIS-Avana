<?php

namespace App\Observers;

use App\Models\PayrollRun;
use App\Support\Notifier;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Releases notifications for payslips that are already due when a payroll run
 * is finalized. Future H-1 releases are picked up by the scheduled command.
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

        Notifier::payslipsReleased($run);
    }
}
