<?php

namespace App\Services;

use App\Models\EmployeeContract;
use App\Models\EmployeeSalaryComponent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Writes one component's nominal for an employee as a new salary version.
 *
 * A salary is never overwritten: the row in force is closed the day before the
 * new one starts, so the payslips already produced keep computing from the
 * figure that was in force when they ran. Every version carries why it changed,
 * who wrote it, which contract it belongs to and which Master Gaji it came
 * from, which is what makes a raise auditable after the fact.
 */
final class EmployeeSalaryWriter
{
    /**
     * Version one component's nominal for an employee.
     *
     * A row already starting on the same date is corrected in place — that is a
     * typo being fixed, not a raise. Anything earlier is closed the day before
     * the new figure takes effect, and rows that would start later are left
     * alone so a future-dated raise is not silently undone.
     */
    public static function record(
        int $tenantId,
        int $employeeId,
        int $componentId,
        float $amount,
        Carbon $from,
        ?string $reason = null,
        ?int $actorId = null,
        ?int $salaryMasterId = null,
        string $status = 'active',
    ): EmployeeSalaryComponent {
        $scope = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->where('payroll_component_id', $componentId);

        $sameDay = (clone $scope)
            ->where('status', $status)
            ->whereDate('effective_start_date', $from->toDateString())
            ->first();

        if ($sameDay !== null) {
            $sameDay->amount = $amount;
            $sameDay->updated_by = $actorId ?? $sameDay->updated_by;
            $sameDay->reason = $reason ?? $sameDay->reason;
            $sameDay->salary_master_id = $salaryMasterId ?? $sameDay->salary_master_id;
            $sameDay->save();

            return $sameDay;
        }

        // A version still waiting for approval pays nothing, so it must not
        // close the figure currently in force — that only happens once it is
        // approved (see {@see self::approve()}).
        if ($status === 'active') {
            self::closePredecessors($scope, $from);
        }

        return EmployeeSalaryComponent::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'employee_contract_id' => self::contractOn($tenantId, $employeeId, $from),
            'salary_master_id' => $salaryMasterId,
            'payroll_component_id' => $componentId,
            'amount' => $amount,
            'status' => $status,
            'effective_start_date' => $from->toDateString(),
            'reason' => $reason,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * Put a pending version into force: the figure it replaces is closed the
     * day before it starts, and the version itself starts paying.
     *
     * The approver is recorded on the row — with the author already stored,
     * that is the four-eyes evidence the audit requirements ask for.
     */
    public static function approve(EmployeeSalaryComponent $version, int $approverId): void
    {
        $from = Carbon::parse($version->effective_start_date ?? now())->startOfDay();

        $scope = EmployeeSalaryComponent::forTenant((int) $version->tenant_id)
            ->where('employee_id', $version->employee_id)
            ->where('payroll_component_id', $version->payroll_component_id)
            ->whereKeyNot($version->getKey());

        // A version already starting on the same day cannot be "closed the day
        // before" — it would end before it began and both rows would pay. The
        // approved figure replaces it outright.
        (clone $scope)
            ->where('status', 'active')
            ->whereDate('effective_start_date', $from->toDateString())
            ->update(['status' => 'cancelled']);

        self::closePredecessors($scope, $from);

        $version->update([
            'status' => 'active',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Close whatever is in force on the day the new figure starts.
     *
     * @param  Builder<EmployeeSalaryComponent>  $scope
     */
    private static function closePredecessors($scope, Carbon $from): void
    {
        (clone $scope)
            ->where(fn ($query) => $query
                ->whereNull('effective_start_date')
                ->orWhereDate('effective_start_date', '<', $from->toDateString()))
            ->where(fn ($query) => $query
                ->whereNull('effective_end_date')
                ->orWhereDate('effective_end_date', '>=', $from->toDateString()))
            ->update(['effective_end_date' => $from->copy()->subDay()->toDateString()]);
    }

    /**
     * The contract the salary belongs to: the one running on the day it takes
     * effect, so a renewal starts a salary of its own rather than extending the
     * contract that ended.
     */
    private static function contractOn(int $tenantId, int $employeeId, Carbon $on): ?int
    {
        $date = $on->toDateString();

        return EmployeeContract::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', $date))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->orderByDesc('start_date')
            ->value('id');
    }
}
