<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Tenant;
use App\Support\Roster;
use App\Support\Workday;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Writes the Alpa (status 'absent') row for an employee who was due at work
 * and never clocked in.
 *
 * Without it a no-show left no trace at all: attendance rows only ever came
 * from a clock-in, an approved leave or an approved correction, so the
 * Absensi rekap, the reports and payroll all read a missed day as nothing.
 *
 * The row is a claim that can be overturned. A late clock-in on a night shift
 * (worked on the previous date) or an approved attendance correction finds the
 * row by date and replaces its status, since neither refuses an existing row
 * that has no clock-in.
 */
final class AbsenceMarker
{
    /**
     * Mark every due employee of the tenant who has no attendance row on the
     * date. Returns how many Alpa rows were written.
     *
     * Only for a tenant that runs the Attendance module: a company that does
     * not use clock-in at all would otherwise see its whole workforce marked
     * absent — and docked in payroll — every day.
     */
    public static function markTenant(int $tenantId, CarbonInterface $date): int
    {
        if (Tenant::find($tenantId)?->hasFeature('attendance') !== true) {
            return 0;
        }

        $marked = 0;

        foreach (self::missing($tenantId, $date) as $employee) {
            Attendance::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'branch_id' => $employee->branch_id,
                'shift_id' => Roster::shiftFor($tenantId, (int) $employee->id, $date)?->id,
                'date' => $date->toDateString(),
                'status' => 'absent',
                'late_minutes' => 0,
                'work_minutes' => 0,
            ]);

            $marked++;
        }

        return $marked;
    }

    /**
     * Employees who were due on the date and have no attendance row for it.
     *
     * On a closed day this is who gets marked Alpa; on today it is the live
     * "belum absen" list. The optional scope narrows the employees first —
     * a branch filter, or the viewer's own branch access.
     *
     * Only employees with a login are considered: someone with no account has
     * no way to clock in, so holding the missing record against them would
     * be the system's fault, not theirs.
     *
     * @param  (callable(Builder<Employee>): mixed)|null  $scope
     * @return Collection<int, Employee>
     */
    public static function missing(int $tenantId, CarbonInterface $date, ?callable $scope = null): Collection
    {
        $dateString = $date->toDateString();

        $query = Employee::forTenant($tenantId)
            ->whereNotNull('user_id')
            ->where(function (Builder $query) use ($dateString): void {
                // Someone who resigned later than this date was still on the
                // books for it — a backfill must not skip their last weeks.
                $query->where('status', 'active')
                    ->orWhereDate('resign_date', '>=', $dateString);
            })
            ->where(function (Builder $query) use ($dateString): void {
                $query->whereNull('join_date')->orWhereDate('join_date', '<=', $dateString);
            });

        if ($scope !== null) {
            $scope($query);
        }

        $employees = $query->get(['id', 'tenant_id', 'branch_id', 'user_id', 'full_name', 'employee_number', 'join_date', 'resign_date']);

        if ($employees->isEmpty()) {
            return $employees;
        }

        $recorded = Attendance::forTenant($tenantId)
            ->whereDate('date', $dateString)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->pluck('employee_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return Workday::dueAmong(
            $employees->reject(fn (Employee $employee): bool => in_array((int) $employee->id, $recorded, true))->values(),
            $date,
        );
    }
}
