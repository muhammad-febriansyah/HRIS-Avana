<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\ShiftSchedule;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Jadwal Saya" — the employee's shift week, plus who on their team is away.
 * A day resolves to a shift, an explicit day off, or "belum dijadwalkan" when
 * no row exists.
 */
class EssScheduleController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * @var array<int, string>
     */
    private const DAY_LABELS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    /**
     * @var array<int, string>
     */
    private const DAY_SHORT = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    /**
     * One week of the employee's schedule, defaulting to the current week.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $start = $request->query('start');
        $weekStart = (is_string($start) && $start !== '' ? Carbon::parse($start) : now())->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $schedules = ShiftSchedule::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekEnd->toDateString())
            ->with('shift:id,name,start_time,end_time')
            ->get()
            ->keyBy(fn (ShiftSchedule $schedule): string => $schedule->date->toDateString());

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->copy()->addDays($offset);
            $days[] = $this->shapeDay($date, $schedules->get($date->toDateString()));
        }

        return Inertia::render('avana/saya/jadwal', [
            'days' => $days,
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekEnd->toDateString(),
            'awayThisWeek' => $this->awayThisWeek($employee->tenant_id, $employee->department_id, $weekStart, $weekEnd),
        ]);
    }

    /**
     * Approved leave among the employee's own department during the week.
     *
     * @return array<int, array<string, mixed>>
     */
    private function awayThisWeek(int $tenantId, ?int $departmentId, CarbonInterface $start, CarbonInterface $end): array
    {
        return LeaveRequest::forTenant($tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->when($departmentId, fn ($query, $id) => $query->whereHas(
                'employee',
                fn ($employeeQuery) => $employeeQuery->where('department_id', $id),
            ))
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->orderBy('start_date')
            ->limit(20)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'name' => $leave->employee?->full_name,
                'leave_type' => $leave->leaveType?->name,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeDay(CarbonInterface $date, ?ShiftSchedule $schedule): array
    {
        $shift = $schedule?->shift;
        $iso = $date->dayOfWeekIso;

        return [
            'date' => $date->toDateString(),
            'day_label' => self::DAY_LABELS[$iso],
            'day_short' => self::DAY_SHORT[$iso],
            'is_today' => $date->isToday(),
            'is_scheduled' => $schedule !== null,
            'is_off' => $schedule !== null && $shift === null,
            'shift_name' => $shift?->name,
            'start' => $this->shortTime($shift?->start_time),
            'end' => $this->shortTime($shift?->end_time),
        ];
    }

    /**
     * Trim a stored H:i:s time down to H:i.
     */
    private function shortTime(?string $time): ?string
    {
        return ($time === null || $time === '') ? null : substr($time, 0, 5);
    }
}
