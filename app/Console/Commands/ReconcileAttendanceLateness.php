<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Support\Roster;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avana:reconcile-attendance-lateness {--tenant= : Tenant ID; every tenant when omitted} {--from= : Y-m-d, defaults to 90 days ago} {--to= : Y-m-d, defaults to today}')]
#[Description('Re-judge attendance rows still stuck at their as-clocked-in state against the roster shift now on record for their date — a one-off backfill for rows punched in before the roster caught up.')]
class ReconcileAttendanceLateness extends Command
{
    /**
     * Walk every present/late attendance in range and re-judge it against
     * whatever shift the roster now has for that employee and date, catching
     * up rows that were punched in before the roster was assigned.
     */
    public function handle(): int
    {
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        $from = $this->option('from') ?? now()->subDays(90)->toDateString();
        $to = $this->option('to') ?? now()->toDateString();

        $checked = 0;
        $changed = 0;

        foreach ($tenantIds as $tenantId) {
            $shifts = Shift::forTenant($tenantId)->get()->keyBy('id');

            Attendance::forTenant($tenantId)
                ->whereNotNull('clock_in_at')
                ->whereIn('status', ['present', 'late'])
                ->whereDate('date', '>=', $from)
                ->whereDate('date', '<=', $to)
                ->orderBy('date')
                ->chunkById(200, function ($attendances) use ($tenantId, $shifts, &$checked, &$changed): void {
                    foreach ($attendances as $attendance) {
                        $checked++;

                        $schedule = ShiftSchedule::forTenant($tenantId)
                            ->where('employee_id', $attendance->employee_id)
                            ->whereDate('date', $attendance->date)
                            ->first();

                        if ($schedule === null) {
                            continue;
                        }

                        if ((int) ($schedule->shift_id ?? 0) === (int) ($attendance->shift_id ?? 0)) {
                            continue;
                        }

                        Roster::reconcileAttendance(
                            $tenantId,
                            (int) $attendance->employee_id,
                            $attendance->date->format('Y-m-d'),
                            $schedule->shift_id !== null ? $shifts->get($schedule->shift_id) : null,
                        );

                        $changed++;
                    }
                });
        }

        $this->info("Checked {$checked} attendance row(s), reconciled {$changed}.");

        return self::SUCCESS;
    }
}
