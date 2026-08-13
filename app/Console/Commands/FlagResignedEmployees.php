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
     *
     * Saved one row at a time rather than a single mass update: a mass update
     * doesn't fire Eloquent's `saved` event, so EmployeeObserver would never
     * run — the employee's login would stay active and their email would
     * never be freed for a new hire to reuse.
     */
    public function handle(): int
    {
        $employees = Employee::query()
            ->where('status', 'active')
            ->whereNotNull('resign_date')
            ->whereDate('resign_date', '<', Carbon::today())
            ->get();

        $employees->each(fn (Employee $employee) => $employee->update(['status' => 'inactive']));

        $this->info("Deactivated {$employees->count()} resigned employee(s).");

        return self::SUCCESS;
    }
}
