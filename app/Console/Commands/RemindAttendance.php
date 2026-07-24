<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\FcmService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('avana:remind-attendance')]
#[Description('Push a clock-in reminder to active employees who have not clocked in yet today.')]
class RemindAttendance extends Command
{
    /**
     * Notify (via FCM) every active employee with a linked user who has no
     * clock-in recorded for today. Skips weekends and is a no-op when FCM is
     * not configured.
     */
    public function handle(FcmService $fcm): int
    {
        if (! $fcm->enabled()) {
            $this->info('FCM not configured; skipping.');

            return self::SUCCESS;
        }

        $today = Carbon::today();

        if ($today->isWeekend()) {
            $this->info('Weekend; skipping.');

            return self::SUCCESS;
        }

        $clockedInEmployeeIds = Attendance::query()
            ->where('date', $today->toDateString())
            ->whereNotNull('clock_in_at')
            ->pluck('employee_id');

        $employees = Employee::query()
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->whereNotIn('id', $clockedInEmployeeIds)
            ->get(['id', 'user_id']);

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
}
