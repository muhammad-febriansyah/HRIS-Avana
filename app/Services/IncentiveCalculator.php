<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\IncentiveAssignment;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveRule;
use App\Models\IncentiveScheme;
use App\Models\PayrollPeriod;
use App\Models\PerformanceReview;
use App\Support\SalaryCompliance;
use Illuminate\Support\Collection;

/**
 * Turns an incentive scheme into one draft calculation per assigned employee
 * for a payroll period.
 *
 * Every row keeps the rule that produced it and the figure it was measured
 * from, because a scheme edited next quarter must not change what a past period
 * paid. Recalculating a period rewrites only rows nobody has approved yet: an
 * approved or locked amount is a decision, not a cache.
 */
final class IncentiveCalculator
{
    /**
     * Compute (or recompute) a scheme's incentives for a period.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function calculate(IncentiveScheme $scheme, PayrollPeriod $period, ?int $actorId = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $on = $period->end_date ?? $period->start_date;

        $employees = $this->assignedEmployees($scheme, $period);

        foreach ($employees as $employee) {
            $existing = IncentiveCalculation::query()
                ->where('incentive_scheme_id', $scheme->id)
                ->where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->first();

            // Approved and locked rows are decisions somebody signed; a
            // recalculation leaves them alone rather than quietly restating
            // what was already agreed.
            if ($existing !== null && ! in_array($existing->status, [IncentiveCalculation::STATUS_DRAFT, IncentiveCalculation::STATUS_REJECTED], true)) {
                $result['skipped']++;

                continue;
            }

            $measured = $this->measure($scheme, $employee, $period);
            [$amount, $rule] = $this->amountFor($scheme, $employee, $measured);

            $amount = $this->prorate($scheme, $employee, $period, $amount);
            $amount = $this->round($scheme, $amount);

            $snapshot = [
                'basis' => $scheme->basis,
                'measured_value' => $measured,
                'rule' => $rule === null ? null : [
                    'id' => $rule->id,
                    'sequence' => $rule->sequence,
                    'min_value' => $rule->min_value === null ? null : (float) $rule->min_value,
                    'max_value' => $rule->max_value === null ? null : (float) $rule->max_value,
                    'amount_type' => $rule->amount_type,
                    'amount' => (float) $rule->amount,
                ],
                'rounding' => $scheme->rounding,
                'rounding_unit' => (int) $scheme->rounding_unit,
                'prorated' => (bool) $scheme->prorate_partial_period,
                'period' => [
                    'id' => $period->id,
                    'start_date' => $period->start_date?->toDateString(),
                    'end_date' => $on?->toDateString(),
                ],
            ];

            $payload = [
                'tenant_id' => $scheme->tenant_id,
                'incentive_scheme_id' => $scheme->id,
                'employee_id' => $employee->id,
                'payroll_period_id' => $period->id,
                'measured_value' => $measured,
                'amount' => $amount,
                'computed_amount' => $amount,
                'source_snapshot' => $snapshot,
                'status' => IncentiveCalculation::STATUS_DRAFT,
                'created_by' => $actorId,
                // A recalculated row starts its review again from the top.
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
            ];

            if ($existing === null) {
                IncentiveCalculation::create($payload);
                $result['created']++;

                continue;
            }

            $existing->update($payload);
            $result['updated']++;
        }

        return $result;
    }

    /**
     * Employees the scheme is assigned to for this period, skipping anyone who
     * is no longer active.
     *
     * @return Collection<int, Employee>
     */
    public function assignedEmployees(IncentiveScheme $scheme, PayrollPeriod $period): Collection
    {
        $on = ($period->end_date ?? $period->start_date)?->toDateString();

        $employeeIds = IncentiveAssignment::query()
            ->where('incentive_scheme_id', $scheme->id)
            ->effectiveOn($on)
            ->pluck('employee_id')
            ->unique()
            ->all();

        if ($employeeIds === []) {
            return collect();
        }

        return Employee::forTenant($scheme->tenant_id)
            ->whereIn('id', $employeeIds)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();
    }

    /**
     * The figure the scheme measures for this employee in this period. A manual
     * scheme measures nothing — HR types the rupiah.
     */
    public function measure(IncentiveScheme $scheme, Employee $employee, PayrollPeriod $period): float
    {
        return match ($scheme->basis) {
            IncentiveScheme::BASIS_ATTENDANCE => (float) Attendance::forTenant($scheme->tenant_id)
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [
                    $period->start_date?->toDateString(),
                    $period->end_date?->toDateString(),
                ])
                ->whereIn('status', ['present', 'late'])
                ->count(),
            // Tied to the period being paid: "the latest review with a score"
            // would pay this quarter's incentive on last year's appraisal, and
            // would change retroactively the next time somebody was reviewed.
            IncentiveScheme::BASIS_PERFORMANCE => (float) (PerformanceReview::query()
                ->where('tenant_id', $scheme->tenant_id)
                ->where('employee_id', $employee->id)
                // Money only ever follows a finalized, calibrated rating —
                // never a provisional one, and never a reopened one.
                ->publishable()
                ->when(
                    $period->start_date !== null && $period->end_date !== null,
                    fn ($query) => $query->whereBetween('review_date', [
                        $period->start_date->toDateString(),
                        $period->end_date->toDateString(),
                    ]),
                )
                ->orderByDesc('review_date')
                ->orderByDesc('id')
                ->value('final_score') ?? 0),
            // A target scheme reads the figure HR recorded on the draft row, so
            // recalculating keeps whatever was entered.
            IncentiveScheme::BASIS_TARGET => (float) (IncentiveCalculation::query()
                ->where('incentive_scheme_id', $scheme->id)
                ->where('employee_id', $employee->id)
                ->where('payroll_period_id', $period->id)
                ->value('measured_value') ?? 0),
            default => 0.0,
        };
    }

    /**
     * The rupiah a measured figure earns, and the rule it came from.
     *
     * @return array{0: float, 1: IncentiveRule|null}
     */
    public function amountFor(IncentiveScheme $scheme, Employee $employee, float $measured): array
    {
        if ($scheme->basis === IncentiveScheme::BASIS_MANUAL) {
            return [0.0, null];
        }

        $rule = $scheme->rules->first(fn (IncentiveRule $candidate): bool => $candidate->covers($measured));

        if ($rule === null) {
            return [0.0, null];
        }

        $amount = match ($rule->amount_type) {
            IncentiveRule::AMOUNT_PER_UNIT => (float) $rule->amount * $measured,
            IncentiveRule::AMOUNT_PERCENT_OF_BASIC => (float) $rule->amount / 100
                * SalaryCompliance::monthlyWage($employee, (int) $scheme->tenant_id)['basic'],
            default => (float) $rule->amount,
        };

        return [$amount, $rule];
    }

    /**
     * Scale a joiner's or leaver's incentive by the share of the period they
     * were employed for, when the scheme asks for it.
     */
    private function prorate(IncentiveScheme $scheme, Employee $employee, PayrollPeriod $period, float $amount): float
    {
        if (! $scheme->prorate_partial_period || $amount <= 0.0) {
            return $amount;
        }

        $start = $period->start_date;
        $end = $period->end_date;

        if ($start === null || $end === null) {
            return $amount;
        }

        $days = $start->diffInDays($end) + 1;

        if ($days <= 0) {
            return $amount;
        }

        $from = $employee->join_date !== null && $employee->join_date->greaterThan($start)
            ? $employee->join_date->copy()
            : $start->copy();

        $to = $employee->resign_date !== null && $employee->resign_date->lessThan($end)
            ? $employee->resign_date->copy()
            : $end->copy();

        if ($to->lessThan($from)) {
            return 0.0;
        }

        $worked = $from->diffInDays($to) + 1;

        return $amount * min(1.0, $worked / $days);
    }

    /** Apply the scheme's rounding to a computed amount. */
    private function round(IncentiveScheme $scheme, float $amount): float
    {
        $unit = max(1, (int) $scheme->rounding_unit);

        return match ($scheme->rounding) {
            'nearest' => round($amount / $unit) * $unit,
            'up' => ceil($amount / $unit) * $unit,
            'down' => floor($amount / $unit) * $unit,
            default => round($amount),
        };
    }
}
