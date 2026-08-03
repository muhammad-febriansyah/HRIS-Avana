<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\FcmService;
use App\Services\LeaveAttendanceMarker;
use App\Support\Roster;
use App\Support\TenantTime;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('avana:remind-attendance')]
#[Description('Push a clock-in reminder to active employees who have not clocked in yet today.')]
class RemindAttendance extends Command
{
    /**
     * The hour, on the tenant's own clock, a reminder goes out.
     */
    private const REMINDER_HOUR = 8;

    /**
     * Notify (via FCM) every employee the roster expects at work today who has
     * no clock-in recorded yet. A no-op when FCM is not configured.
     *
     * The roster decides who is due, not the calendar: a shift company works
     * weekends, and someone rostered off — or on approved leave — has nothing
     * to be reminded about. Employees with no roster row at all still get the
     * reminder, since a tenant that does not use the roster runs a plain
     * office week and would otherwise never be reminded of anything.
     */
    public function handle(FcmService $fcm): int
    {
        if (! $fcm->enabled()) {
            $this->info('FCM not configured; skipping.');

            return self::SUCCESS;
        }

        $employees = $this->dueEmployees();

        if ($employees->isEmpty()) {
            $this->info('No one to remind.');

            return self::SUCCESS;
        }

        $fcm->pushToUsers(
            $employees->pluck('user_id')->all(),
            'Jangan lupa absen',
            'Anda belum melakukan clock-in hari ini.',
            ['type' => 'attendance', 'id' => 0],
        );

        $this->info('Reminded '.$employees->count().' employee(s).');

        return self::SUCCESS;
    }

    /**
     * Everyone the roster expects at work today who has not clocked in yet.
     *
     * Kept apart from the push itself so who gets reminded can be checked
     * without standing up a notification sender.
     *
     * Passing a date reminds every tenant for that date, which is what a test
     * wants. Passing nothing — how the schedule calls it — asks each tenant
     * its own clock instead: the command runs every hour, and a tenant is
     * reminded on the pass where its own wall clock reads the reminder hour.
     * One WIB schedule pushed "you have not clocked in" to a Jayapura office
     * at 10:30 their time, two and a half hours after they should have been at
     * their desk, and to a Makassar office an hour late.
     *
     * @return Collection<int, Employee>
     */
    public function dueEmployees(?CarbonInterface $today = null): Collection
    {
        if ($today !== null) {
            return $this->dueOn($this->activeEmployees(), $today);
        }

        return $this->activeEmployees()
            ->groupBy('tenant_id')
            ->flatMap(function (Collection $employees, int|string $tenantId): Collection {
                $localNow = TenantTime::now($tenantId);

                if ($localNow->hour !== self::REMINDER_HOUR) {
                    return collect();
                }

                return $this->dueOn($employees, $localNow->copy()->startOfDay());
            })
            ->values();
    }

    /**
     * Active employees who can be pushed to at all.
     *
     * @return Collection<int, Employee>
     */
    private function activeEmployees(): Collection
    {
        return Employee::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->get(['id', 'tenant_id', 'user_id']);
    }

    /**
     * Narrow a set of employees to those due on the given date and not yet in.
     *
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, Employee>
     */
    private function dueOn(Collection $employees, CarbonInterface $today): Collection
    {
        $clockedInEmployeeIds = Attendance::query()
            ->where('date', $today->toDateString())
            ->whereNotNull('clock_in_at')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->pluck('employee_id')
            ->all();

        return $employees
            ->reject(fn (Employee $employee): bool => in_array($employee->id, $clockedInEmployeeIds, true))
            ->filter(fn (Employee $employee): bool => $this->isDueToday($employee, $today))
            ->values();
    }

    /**
     * Whether today is a working day for this employee.
     *
     * An approved leave answers first — someone on cuti is not late, they are
     * away. Then the roster: a row with a shift means they are due, a row with
     * no shift is a day off. No row at all falls back to the ordinary Monday
     * to Friday week.
     */
    private function isDueToday(Employee $employee, CarbonInterface $today): bool
    {
        if (LeaveAttendanceMarker::covers($employee->tenant_id, $employee->id, $today->toDateString())) {
            return false;
        }

        $schedule = Roster::scheduleFor((int) $employee->tenant_id, (int) $employee->id, $today);

        if ($schedule !== null) {
            return $schedule->shift_id !== null;
        }

        return ! $today->isWeekend();
    }
}
