<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AttendanceFinalizer;
use App\Support\TenantTime;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:finalize-attendance {--from=} {--to=} {--tenant=} {--employee=} {--dry-run}')]
#[Description('Finalize due attendance rows from employee roster assignments.')]
final class FinalizeAttendance extends Command
{
    public function handle(AttendanceFinalizer $finalizer): int
    {
        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $employeeId = $this->option('employee') !== null ? (int) $this->option('employee') : null;

        $tenantIds = Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->whereKey($tenantId))
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('id');

        if ($tenantIds->isEmpty()) {
            $this->error('No active tenant matched the requested scope.');

            return self::FAILURE;
        }

        foreach ($tenantIds as $currentTenantId) {
            $today = Carbon::parse(TenantTime::today($currentTenantId));
            $from = Carbon::parse($this->option('from') ?: $today->copy()->subDays(2))->toDateString();
            $to = Carbon::parse($this->option('to') ?: $today)->toDateString();

            if ($to < $from) {
                $this->error('The --to date must be on or after --from.');

                return self::FAILURE;
            }

            $counts = $finalizer->finalizeRange(
                (int) $currentTenantId,
                $from,
                $to,
                $employeeId,
                dryRun: (bool) $this->option('dry-run'),
            );

            $this->line(sprintf(
                'Tenant %d [%s..%s]: due=%d absent=%d incomplete=%d complete=%d leave=%d not_due=%d%s',
                $currentTenantId,
                $from,
                $to,
                $counts['due'],
                $counts['absent'],
                $counts['incomplete'],
                $counts['complete'],
                $counts['leave'],
                $counts['not_due'],
                $this->option('dry-run') ? ' (dry run)' : '',
            ));
        }

        return self::SUCCESS;
    }
}
