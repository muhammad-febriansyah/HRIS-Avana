<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RosterController extends Controller
{
    use AuthorizesRequests;

    /**
     * Indonesian short day-of-week labels keyed by Carbon's dayOfWeek (0 = Sunday).
     *
     * @var array<int, string>
     */
    private const DAY_LABELS = [
        0 => 'Min', 1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab',
    ];

    /**
     * Indonesian short month labels keyed by month number.
     *
     * @var array<int, string>
     */
    private const MONTH_LABELS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /**
     * Display the weekly shift roster grid for the tenant's active employees.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $weekStart = $this->resolveWeekStart($request->query('week_start'));
        $weekEnd = $weekStart->copy()->addDays(6);

        $week = collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart): array {
                $day = $weekStart->copy()->addDays($offset);

                return [
                    'date' => $day->format('Y-m-d'),
                    'dow' => self::DAY_LABELS[$day->dayOfWeek],
                    'day' => $day->day,
                    'label' => $day->day.' '.self::MONTH_LABELS[$day->month],
                ];
            })
            ->all();

        return Inertia::render('avana/roster', [
            'employees' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
            'shifts' => Shift::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('start_time')
                ->get(['id', 'code', 'name', 'start_time', 'end_time'])
                ->map(fn (Shift $shift): array => [
                    'id' => $shift->id,
                    'code' => $shift->code,
                    'name' => $shift->name,
                    'start_time' => substr((string) $shift->start_time, 0, 5),
                    'end_time' => substr((string) $shift->end_time, 0, 5),
                ]),
            'schedules' => ShiftSchedule::forTenant($tenantId)
                ->whereDate('date', '>=', $weekStart->format('Y-m-d'))
                ->whereDate('date', '<=', $weekEnd->format('Y-m-d'))
                ->get(['id', 'employee_id', 'shift_id', 'date'])
                ->map(fn (ShiftSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'employee_id' => $schedule->employee_id,
                    'shift_id' => $schedule->shift_id,
                    'date' => $schedule->date->format('Y-m-d'),
                ]),
            'week' => $week,
            'week_start' => $weekStart->format('Y-m-d'),
        ]);
    }

    /**
     * Assign (or reassign) a shift to an employee on a given date.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'shift_id' => ['required', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
        ]);

        // Match on the calendar date rather than the cast value so a single
        // assignment per employee/date is enforced regardless of how the
        // database stores the time component.
        $schedule = ShiftSchedule::forTenant($tenantId)
            ->where('employee_id', $validated['employee_id'])
            ->whereDate('date', $validated['date'])
            ->first();

        if ($schedule !== null) {
            $schedule->update(['shift_id' => $validated['shift_id']]);
        } else {
            ShiftSchedule::create([
                'tenant_id' => $tenantId,
                'employee_id' => $validated['employee_id'],
                'shift_id' => $validated['shift_id'],
                'date' => $validated['date'],
            ]);
        }

        return back()->with('success', 'Jadwal shift disimpan');
    }

    /**
     * Remove a shift assignment from the roster.
     */
    public function destroy(Request $request, ShiftSchedule $schedule): RedirectResponse
    {
        $this->authorize('viewAny', Attendance::class);

        abort_if((int) $schedule->tenant_id !== (int) $request->user()->tenant_id, 404);

        $schedule->delete();

        return back()->with('success', 'Jadwal dihapus');
    }

    /**
     * Resolve the Monday that starts the requested week, defaulting to the
     * current week when the query parameter is missing or invalid.
     */
    private function resolveWeekStart(?string $input): Carbon
    {
        if (is_string($input) && $input !== '') {
            try {
                return Carbon::createFromFormat('Y-m-d', $input)
                    ->startOfDay()
                    ->startOfWeek(Carbon::MONDAY);
            } catch (\Throwable) {
                // Fall through to the current week on an unparseable value.
            }
        }

        return Carbon::today()->startOfWeek(Carbon::MONDAY);
    }
}
