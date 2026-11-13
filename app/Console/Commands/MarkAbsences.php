<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AbsenceMarker;
use App\Support\TenantTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:mark-absences
    {--from= : First date to mark (Y-m-d) — a backfill; needs --to}
    {--to= : Last date to mark (Y-m-d)}
    {--tenant= : Limit to one tenant id}')]
#[Description('Mark employees who were due at work but never clocked in as Alpa (absent).')]
class MarkAbsences extends Command
{
    /**
     * The hour, on the tenant's own clock, yesterday is closed and marked.
     *
     * Past midnight rather than at it, so a night shift that started the
     * evening before has had its chance to clock in.
     */
    private const MARK_HOUR = 1;

    /**
     * Run without options (how the schedule calls it), each tenant has its
     * previous day marked on the pass where its own wall clock reads the mark
     * hour. With --from/--to every date in the range is marked for every
     * tenant now — a backfill, which skips anyone who already has a row.
     */
    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $tenantId) => $query->whereKey($tenantId))
            ->pluck('id');

        $from = $this->option('from');
        $to = $this->option('to');

        if ($from !== null || $to !== null) {
            return $this->backfill($tenants->values()->all(), (string) $from, (string) $to);
        }

        $marked = 0;

        foreach ($tenants as $tenantId) {
            $localNow = TenantTime::now($tenantId);

            if ($localNow->hour !== self::MARK_HOUR) {
                continue;
            }

            $marked += AbsenceMarker::markTenant((int) $tenantId, $localNow->copy()->subDay()->startOfDay());
        }

        $this->info("Marked {$marked} absence(s).");

        return self::SUCCESS;
    }

    /**
     * Mark every date in an inclusive range, never past yesterday: today is
     * still open, and a clock-in later this morning must not meet an Alpa.
     *
     * @param  array<int, mixed>  $tenantIds
     */
    private function backfill(array $tenantIds, string $from, string $to): int
    {
        if ($from === '' || $to === '') {
            $this->error('Isi --from dan --to sekaligus (Y-m-d).');

            return self::FAILURE;
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $marked = 0;

        foreach ($tenantIds as $tenantId) {
            $lastClosed = TenantTime::startOfToday($tenantId)->subDay();
            $last = $end->gt($lastClosed) ? $lastClosed : $end;

            for ($date = $start->copy(); $date->lte($last); $date->addDay()) {
                $marked += AbsenceMarker::markTenant((int) $tenantId, $date);
            }
        }

        $this->info("Marked {$marked} absence(s).");

        return self::SUCCESS;
    }
}
