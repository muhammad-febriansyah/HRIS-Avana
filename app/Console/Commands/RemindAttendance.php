<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\FcmService;
use App\Services\LeaveAttendanceMarker;
use App\Support\Roster;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

#[Signature('avana:remind-attendance')]
#[Description('Push a clock-in reminder to active employees who have not clocked in yet today.')]
class RemindAttendance extends Command
{
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

        $employees = $this->dueEmployees(Carbon::today());

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
     * @return Collection<int, Employee>
     */
    public function dueEmployees(CarbonInterface $today): Collection
    {
        $clockedInEmployeeIds = Attendance::query()
            ->where('date', $today->toDateString())
            ->whereNotNull('clock_in_at')
            ->pluck('employee_id');

        return Employee::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->whereNotIn('id', $clockedInEmployeeIds)
            ->get(['id', 'tenant_id', 'user_id'])
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
