<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Support\SalaryCompliance;

/**
 * Prices one timesheet entry: what the hours cost the company, and what they
 * are worth to the client.
 *
 * Both rates are resolved the same way — the employee's assignment on the
 * project beats the project's default — and only the cost rate has a further
 * fallback, derived from the employee's own monthly wage. A bill rate that
 * nobody set stays null: a company that does not sell hours should read zero
 * revenue, not an invented figure.
 *
 * The resolved rates are frozen onto the entry when it is written, so a later
 * raise or a re-priced project does not silently rewrite last quarter's
 * profitability report.
 */
final class TimesheetCosting
{
    /**
     * Hours in a working month, per Kepmenaker 102/2004 — the same 1/173
     * divisor the overtime rules use to turn a monthly wage into an hourly one.
     */
    public const MONTHLY_HOURS = 173;

    /**
     * Derived hourly cost per employee id, for the life of the request. A
     * report prices hundreds of entries and the wage lookup walks the salary
     * components each time.
     *
     * @var array<int, float>
     */
    private static array $wageCache = [];

    /**
     * The bill and cost rate that apply to this employee on this project.
     *
     * @return array{bill_rate: float|null, cost_rate: float|null}
     */
    public static function ratesFor(Employee $employee, Project $project): array
    {
        $member = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('employee_id', $employee->id)
            ->first();

        $billRate = self::firstPositive([
            $member?->bill_rate,
            $project->default_bill_rate,
        ]);

        $costRate = self::firstPositive([
            $member?->cost_rate,
            $project->default_cost_rate,
            self::hourlyWage($employee),
        ]);

        return ['bill_rate' => $billRate, 'cost_rate' => $costRate];
    }

    /**
     * The costing columns for an entry of `$hours` on this project.
     *
     * A project marked non-billable prices no revenue at all, whatever rate is
     * on file — the switch is the whole point of internal projects.
     *
     * @return array{is_billable: bool, bill_rate: float|null, cost_rate: float|null, bill_amount: float, cost_amount: float}
     */
    public static function priceFor(Employee $employee, Project $project, float $hours, ?bool $billable = null): array
    {
        $rates = self::ratesFor($employee, $project);

        $isBillable = $project->is_billable && ($billable ?? true);

        $billAmount = $isBillable && $rates['bill_rate'] !== null
            ? round($rates['bill_rate'] * $hours, 2)
            : 0.0;

        $costAmount = $rates['cost_rate'] !== null
            ? round($rates['cost_rate'] * $hours, 2)
            : 0.0;

        return [
            'is_billable' => $isBillable,
            'bill_rate' => $rates['bill_rate'],
            'cost_rate' => $rates['cost_rate'],
            'bill_amount' => $billAmount,
            'cost_amount' => $costAmount,
        ];
    }

    /**
     * The employee's hourly cost, derived from the fixed monthly wage the
     * payroll setup already holds. Null when no salary is on file — a new hire
     * whose contract has not been entered should not be costed at zero and
     * quietly flatter the project's margin.
     */
    public static function hourlyWage(Employee $employee): ?float
    {
        if (array_key_exists($employee->id, self::$wageCache)) {
            return self::$wageCache[$employee->id] ?: null;
        }

        $monthly = SalaryCompliance::monthlyWage($employee, (int) $employee->tenant_id)['total'];

        $hourly = $monthly > 0.0 ? round($monthly / self::MONTHLY_HOURS, 2) : 0.0;

        self::$wageCache[$employee->id] = $hourly;

        return $hourly ?: null;
    }

    /**
     * Forget the derived wages. Only tests and long-running commands need this;
     * a web request is short enough that the cache cannot go stale within it.
     */
    public static function flushCache(): void
    {
        self::$wageCache = [];
    }

    /**
     * The first value above zero, or null when there is none.
     *
     * @param  array<int, float|string|null>  $candidates
     */
    private static function firstPositive(array $candidates): ?float
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && (float) $candidate > 0.0) {
                return (float) $candidate;
            }
        }

        return null;
    }
}
