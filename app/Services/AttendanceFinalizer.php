<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ShiftSchedule;
use App\Support\AttendanceFines;
use App\Support\Roster;
use App\Support\TenantTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AttendanceFinalizer
{
    /** @var list<string> */
    private const COUNTERS = [
        'due',
        'absent',
        'incomplete',
        'complete',
        'leave',
        'not_due',
    ];

    /**
     * Finalize every due roster row in a date range.
     *
     * @return array<string, int>
     */
    public function finalizeRange(
        int $tenantId,
        string $from,
        string $to,
        ?int $employeeId = null,
        ?CarbonInterface $now = null,
        bool $dryRun = false,
    ): array {
        $counts = array_fill_keys(self::COUNTERS, 0);

        ShiftSchedule::query()
            ->forTenant($tenantId)
            ->whereNotNull('shift_id')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereHas('employee', fn ($query) => $query->where('status', 'active'))
            ->with([
                'employee:id,tenant_id,branch_id,status',
                'shift:id,tenant_id,start_time,end_time,late_tolerance_minutes,deleted_at',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($schedules) use (&$counts, $now, $dryRun): void {
                foreach ($schedules as $schedule) {
                    if ($schedule->employee === null || $schedule->shift === null) {
                        continue;
                    }

                    if (! $this->isDue($schedule, $now)) {
                        $counts['not_due']++;

                        continue;
                    }

                    $counts['due']++;
                    $result = $this->finalizeSchedule($schedule, $dryRun);
                    $counts[$result]++;
                }
            });

        return $counts;
    }

    /** Recalculate existing attendance facts after roster changes. */
    public static function recalculateRange(
        int $tenantId,
        array $employeeIds,
        string $from,
        string $to,
    ): int {
        $updated = 0;

        Attendance::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereDate('date', '<=', TenantTime::today($tenantId))
            ->with('employee:id,tenant_id,branch_id,status')
            ->orderBy('id')
            ->chunkById(200, function ($attendances) use (&$updated): void {
                foreach ($attendances as $attendance) {
                    if (self::recalculate($attendance)) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    /** Recalculate every existing attendance tied to an edited shift master. */
    public static function recalculateShift(int $tenantId, int $shiftId): int
    {
        $updated = 0;

        Attendance::query()
            ->forTenant($tenantId)
            ->where('shift_id', $shiftId)
            ->whereDate('date', '<=', TenantTime::today($tenantId))
            ->with('employee:id,tenant_id,branch_id,status')
            ->orderBy('id')
            ->chunkById(200, function ($attendances) use (&$updated): void {
                foreach ($attendances as $attendance) {
                    if (self::recalculate($attendance)) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    /** Recalculate one attendance row from its current roster assignment. */
    public static function recalculate(Attendance $attendance): bool
    {
        if ($attendance->status === 'leave') {
            return false;
        }

        $schedule = ShiftSchedule::query()
            ->forTenant($attendance->tenant_id)
            ->where('employee_id', $attendance->employee_id)
            ->whereDate('date', $attendance->date)
            ->with('shift')
            ->first();

        $shift = $schedule?->shift;

        if ($attendance->clock_in_at === null) {
            if ($shift === null) {
                AttendanceFines::sync($attendance);
                $attendance->delete();

                return true;
            }

            $attendance->fill([
                'shift_id' => $shift->id,
                'status' => 'absent',
                'late_minutes' => 0,
                'work_minutes' => 0,
            ])->save();
            AttendanceFines::sync($attendance);

            return true;
        }

        $verdict = Roster::evaluate(
            $shift,
            Carbon::parse($attendance->clock_in_at),
            $attendance->date->toDateString(),
        );

        $attendance->fill([
            'shift_id' => $verdict['shift_id'],
            'status' => $attendance->clock_out_at === null ? 'incomplete' : $verdict['status'],
            'late_minutes' => $verdict['late_minutes'],
            'work_minutes' => $attendance->clock_out_at === null
                ? 0
                : max(0, (int) $attendance->clock_in_at->diffInMinutes($attendance->clock_out_at)),
        ])->save();
        AttendanceFines::sync($attendance);

        return true;
    }

    private function isDue(ShiftSchedule $schedule, ?CarbonInterface $now): bool
    {
        $shift = $schedule->shift;

        if ($shift === null || $shift->end_time === null) {
            return false;
        }

        $zone = TenantTime::zoneForBranch($schedule->tenant_id, $schedule->employee?->branch_id);
        $localNow = $now !== null
            ? $now->copy()->setTimezone($zone)
            : Carbon::now($zone);
        $endsAt = Carbon::parse($schedule->date->toDateString(), $zone)
            ->setTimeFromTimeString((string) $shift->end_time);

        if (Roster::crossesMidnight($shift)) {
            $endsAt->addDay();
        }

        return $localNow->greaterThanOrEqualTo(
            $endsAt->addMinutes((int) config('attendance.finalization_grace_minutes', 180)),
        );
    }

    private function finalizeSchedule(ShiftSchedule $schedule, bool $dryRun): string
    {
        $date = $schedule->date->toDateString();

        if (LeaveAttendanceMarker::covers($schedule->tenant_id, $schedule->employee_id, $date)) {
            return 'leave';
        }

        $attendance = Attendance::query()
            ->forTenant($schedule->tenant_id)
            ->where('employee_id', $schedule->employee_id)
            ->whereDate('date', $date)
            ->first();

        $result = match (true) {
            $attendance?->status === 'leave' => 'leave',
            $attendance?->clock_in_at === null => 'absent',
            $attendance->clock_out_at === null => 'incomplete',
            default => 'complete',
        };

        if ($dryRun || $result === 'leave') {
            return $result;
        }

        DB::transaction(function () use ($schedule, $date): void {
            $attendance = Attendance::query()
                ->forTenant($schedule->tenant_id)
                ->where('employee_id', $schedule->employee_id)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            if ($attendance === null) {
                $attendance = Attendance::create([
                    'tenant_id' => $schedule->tenant_id,
                    'employee_id' => $schedule->employee_id,
                    'branch_id' => $schedule->employee?->branch_id,
                    'shift_id' => $schedule->shift_id,
                    'date' => $date,
                    'status' => 'absent',
                    'late_minutes' => 0,
                    'work_minutes' => 0,
                ]);
            }

            self::recalculate($attendance);
        }, 3);

        return $result;
    }
}
