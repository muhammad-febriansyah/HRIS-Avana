<?php

namespace App\Console\Commands;

use App\Models\PayrollRun;
use App\Support\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:remind-payroll-attendance-cutoff')]
#[Description('Remind HR to recalculate early payroll previews after attendance cut-off.')]
class RemindPayrollAttendanceCutoff extends Command
{
    /**
     * Find current engine runs that still carry deferred attendance allowances.
     */
    public function handle(): int
    {
        $notified = 0;

        PayrollRun::query()
            ->current()
            ->where('source', PayrollRun::SOURCE_ENGINE)
            ->whereIn('status', [PayrollRun::STATUS_CALCULATED, PayrollRun::STATUS_APPROVED])
            ->whereHas('period', fn ($period) => $period->where('status', 'draft'))
            ->with([
                'period:id,name,status',
                'items:id,payroll_run_id,calculation_snapshot',
            ])
            ->select(['id', 'tenant_id', 'payroll_period_id', 'status', 'source'])
            ->chunkById(100, function ($runs) use (&$notified): void {
                foreach ($runs as $run) {
                    $cutOff = $this->readyCutOff($run);

                    if ($cutOff !== null) {
                        $notified += Notifier::payrollAttendanceCutOffReady($run, $cutOff);
                    }
                }
            });

        $this->info("Created {$notified} payroll attendance cut-off reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Return the latest deferred cut-off only after every employee window has
     * closed. A recompute can then settle the whole run in one review cycle.
     */
    private function readyCutOff(PayrollRun $run): ?string
    {
        $ends = [];

        foreach ($run->items as $item) {
            $cutOff = ($item->calculation_snapshot ?? [])['attendance_cut_off'] ?? [];

            if (($cutOff['deferred'] ?? []) === []) {
                continue;
            }

            $end = $cutOff['end'] ?? null;

            if (blank($end)) {
                return null;
            }

            $ends[] = (string) $end;
        }

        if ($ends === []) {
            return null;
        }

        $latest = max($ends);

        return Carbon::parse($latest)->endOfDay()->isPast() ? $latest : null;
    }
}
