<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\ShiftSchedule;
use App\Support\Roster;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee self-service work schedule: the week of shift assignments so the app
 * can show today's shift and the week ahead. A day resolves to a shift, an
 * explicit day off, or "not yet scheduled" when no row exists.
 */
class ScheduleController extends Controller
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

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $start = $request->query('start');
        $weekStart = ($start !== null ? Carbon::parse($start) : now())->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $schedules = ShiftSchedule::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $weekStart->toDateString())
            ->whereDate('date', '<=', $weekEnd->toDateString())
            ->with('shift:id,name,start_time,end_time')
            ->get()
            ->keyBy(fn (ShiftSchedule $s): string => $s->date->toDateString());

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $days[] = $this->shapeDay($date, $schedules->get($date->toDateString()));
        }

        return response()->json([
            'data' => $days,
            'meta' => [
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString(),
            ],
        ]);
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
            // A night shift ends the next morning; without this the app shows
            // "23:00 – 07:00" and leaves the reader to guess which day.
            'crosses_midnight' => $shift !== null && Roster::crossesMidnight($shift),
        ];
    }

    private function shortTime(?string $time): ?string
    {
        return ($time === null || $time === '') ? null : substr($time, 0, 5);
    }
}
