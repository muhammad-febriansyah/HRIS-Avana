<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Shift;
use App\Services\AbsenceMarker;
use App\Services\FcmService;
use App\Support\TenantTime;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

#[Signature('avana:remind-clock-out')]
#[Description('Push a clock-out reminder to employees still clocked in half an hour after their shift ended.')]
class RemindClockOut extends Command
{
    /** How long after the shift end the reminder goes out. */
    public const AFTER_SHIFT_END_MINUTES = 30;

    /**
     * Past this, the open day is a forgotten clock-out the next shift has
     * taken over — a reminder then would only ask for a punch the app no
     * longer offers.
     */
    private const STOP_AFTER_HOURS = 6;

    /**
     * Notify (via FCM) every employee whose rostered shift ended at least half
     * an hour ago and who has still not clocked out. Each open day is reminded
     * once. A no-op when FCM is not configured.
     *
     * A forgotten clock-out still leaves the day counted as attended, with
     * only its clock-in; this reminder is there so the worked hours get
     * recorded too.
     */
    public function handle(FcmService $fcm): int
    {
        if (! $fcm->enabled()) {
            $this->info('FCM not configured; skipping.');

            return self::SUCCESS;
        }

        $due = $this->dueAttendances(Carbon::now());

        $fresh = $due->filter(fn (Attendance $attendance): bool => Cache::add(
            'clock-out-reminded:'.$attendance->id,
            true,
            now()->addDay(),
        ));

        $fcm->pushToUsers(
            $fresh->map(fn (Attendance $attendance): ?int => $attendance->employee?->user_id)->filter()->unique()->values()->all(),
            'Jangan lupa absen pulang',
            'Shift Anda sudah selesai, tapi Anda belum clock-out.',
            ['type' => 'attendance', 'id' => 0],
        );

        $this->info('Reminded '.$fresh->count().' employee(s).');

        return self::SUCCESS;
    }

    /**
     * Open days (clocked in, not out) whose shift ended between half an hour
     * and STOP_AFTER_HOURS ago, each tenant read on its own clock. A day with
     * no shift has no end to go by and is left alone.
     *
     * Kept apart from the push so who gets reminded can be checked without a
     * notification sender.
     *
     * @return Collection<int, Attendance>
     */
    public function dueAttendances(CarbonInterface $now): Collection
    {
        $open = Attendance::query()
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->whereNotNull('shift_id')
            ->where('clock_in_at', '>=', $now->copy()->subDays(2))
            ->whereHas('employee', fn ($query) => $query->whereNotNull('user_id')->where('status', 'active'))
            ->with('employee:id,user_id')
            ->get(['id', 'tenant_id', 'employee_id', 'shift_id', 'date']);

        $shifts = Shift::query()
            ->whereIn('id', $open->pluck('shift_id')->unique())
            ->get()
            ->keyBy('id');

        return $open
            ->filter(function (Attendance $attendance) use ($shifts, $now): bool {
                $shift = $shifts->get($attendance->shift_id);

                if ($shift === null || $shift->end_time === null) {
                    return false;
                }

                $zone = TenantTime::zone($attendance->tenant_id);
                $end = AbsenceMarker::shiftEnd(
                    $shift,
                    Carbon::parse($attendance->date->toDateString(), $zone),
                );
                $localNow = $now->copy()->setTimezone($zone);

                return $localNow->greaterThanOrEqualTo($end->copy()->addMinutes(self::AFTER_SHIFT_END_MINUTES))
                    && $localNow->lessThan($end->copy()->addHours(self::STOP_AFTER_HOURS));
            })
            ->values();
    }
}
