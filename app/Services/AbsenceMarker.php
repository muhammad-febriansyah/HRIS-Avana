<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Support\Roster;
use App\Support\Workday;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
     * How many days before today each scheduled run still closes. One would
     * do while the scheduler never misses a beat, but a stopped cron, a
     * deploy or a reboot then left every day it skipped as "Belum Tercatat"
     * for good. Kept short on purpose: anything older is a backfill
     * (`--from/--to`), a choice to dock pay the client has to make.
     */
    public const CATCH_UP_DAYS = 2;

    /**
     * Mark every due employee of the tenant who has no attendance row on the
     * date, whatever the hour — a backfill of days already over. Returns how
     * many Alpa rows were written.
     *
     * Only for a tenant that runs the Attendance module: a company that does
     * not use clock-in at all would otherwise see its whole workforce marked
     * absent — and docked in payroll — every day.
     */
    public static function markTenant(int $tenantId, CarbonInterface $date): int
    {
        if (! self::runsAttendance($tenantId)) {
            return 0;
        }

        $marked = 0;

        foreach (self::missing($tenantId, $date) as $employee) {
            self::write($tenantId, $employee, $date, Roster::shiftFor($tenantId, (int) $employee->id, $date)?->id);
            $marked++;
        }

        return $marked;
    }

    /**
     * Mark Alpa for everyone whose shift has ended without a clock-in, on the
     * work dates still open at the tenant's local time $now: today, and the
     * CATCH_UP_DAYS before it — yesterday for a night shift that ends after
     * midnight, and any day a missed scheduler run left open. Days inside a
     * locked payroll period are left alone.
     *
     * A no-show becomes Alpa the moment their shift is over, not the next
     * morning, so the rekap, the reports and payroll see it the same day.
     * Before the shift ends nobody is Alpa yet — a 15:00 shift is not missing
     * at 10:00. Someone with no roster row works the office week; with no
     * shift to end, their day ends at midnight.
     */
    public static function markEnded(int $tenantId, CarbonInterface $now): int
    {
        if (! self::runsAttendance($tenantId)) {
            return 0;
        }

        $today = $now->copy()->startOfDay();
        $marked = 0;
        $lockedPeriods = PayrollPeriod::forTenant($tenantId)
            ->where('status', 'locked')
            ->whereDate('end_date', '>=', $today->copy()->subDays(self::CATCH_UP_DAYS)->toDateString())
            ->get(['start_date', 'end_date']);

        for ($daysBack = self::CATCH_UP_DAYS; $daysBack >= 0; $daysBack--) {
            $date = $today->copy()->subDays($daysBack);

            // A locked period's payslips are history; an Alpa written into it
            // would show on the rekap yet never reach the pay it should dock.
            if ($lockedPeriods->contains(fn (PayrollPeriod $period): bool => $period->start_date->toDateString() <= $date->toDateString()
                && $period->end_date->toDateString() >= $date->toDateString())) {
                continue;
            }

            $missing = self::missing($tenantId, $date);

            if ($missing->isEmpty()) {
                continue;
            }

            $shifts = ShiftSchedule::forTenant($tenantId)
                ->whereIn('employee_id', $missing->pluck('id'))
                ->whereDate('date', $date->toDateString())
                ->with('shift')
                ->get()
                ->mapWithKeys(fn (ShiftSchedule $row): array => [(int) $row->employee_id => $row->shift]);

            foreach ($missing as $employee) {
                $shift = $shifts->get((int) $employee->id);

                if ($now->lt(self::shiftEnd($shift, $date))) {
                    continue;
                }

                self::write($tenantId, $employee, $date, $shift?->id);
                $marked++;
            }
        }

        return $marked;
    }

    /**
     * When the work day on $date is over: the shift's end time (the next
     * calendar day for a shift that crosses midnight), or midnight when there
     * is no shift to end.
     */
    public static function shiftEnd(?Shift $shift, CarbonInterface $date): Carbon
    {
        $day = Carbon::parse($date->toDateString(), $date->getTimezone());

        if ($shift === null || $shift->end_time === null) {
            return $day->addDay();
        }

        $end = $day->copy()->setTimeFromTimeString((string) $shift->end_time);

        return Roster::crossesMidnight($shift) ? $end->addDay() : $end;
    }

    private static function runsAttendance(int $tenantId): bool
    {
        return Tenant::find($tenantId)?->hasFeature('attendance') === true;
    }

    private static function write(int $tenantId, Employee $employee, CarbonInterface $date, ?int $shiftId): void
    {
        Attendance::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'shift_id' => $shiftId,
            'date' => $date->toDateString(),
            'status' => 'absent',
            'late_minutes' => 0,
            'work_minutes' => 0,
        ]);
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
     * be the system's fault, not theirs. Nor anyone HR marked "tidak wajib
     * absen" on the Kebijakan Absensi page.
     *
     * @param  (callable(Builder<Employee>): mixed)|null  $scope
     * @return Collection<int, Employee>
     */
    public static function missing(int $tenantId, CarbonInterface $date, ?callable $scope = null): Collection
    {
        $dateString = $date->toDateString();

        $query = Employee::forTenant($tenantId)
            ->whereNotNull('user_id')
            // An owner or director the company never expects at the clock.
            ->where('attendance_exempt', false)
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
