<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:flag-resigned-employees')]
#[Description('Deactivate employees whose last working day has passed.')]
class FlagResignedEmployees extends Command
{
    /**
     * Flip still-active employees to inactive once their resign date is in the
     * past. Runs daily so a resignation keeps them payable through their final
     * day, then removes them from the next payroll run.
     */
    public function handle(): int
    {
        $affected = Employee::query()
            ->where('status', 'active')
            ->whereNotNull('resign_date')
            ->whereDate('resign_date', '<', Carbon::today())
            ->update(['status' => 'inactive']);

        $this->info("Deactivated {$affected} resigned employee(s).");

        return self::SUCCESS;
    }
}
