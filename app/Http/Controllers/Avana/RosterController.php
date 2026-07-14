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

        return Inertia::render('avana/roster/index', [
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
     * Bulk-assign one shift to many employees across many dates in a single
     * request — the "isi cepat" workflow for large headcounts. When
     * `employee_ids` is omitted it targets every active employee in the tenant.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'shift_id' => ['required', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'dates' => ['required', 'array', 'min:1', 'max:31'],
            'dates.*' => ['required', 'date'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => [Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
        ]);

        $employeeIds = $validated['employee_ids'] ?? Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->pluck('id')
            ->all();

        if ($employeeIds === []) {
            return back()->with('error', 'Tidak ada karyawan untuk dijadwalkan.');
        }

        $dates = collect($validated['dates'])
            ->map(fn (string $date): string => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->values();

        $existing = ShiftSchedule::forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('date', '>=', $dates->min())
            ->whereDate('date', '<=', $dates->max())
            ->get(['id', 'employee_id', 'date']);

        $existingIds = [];
        foreach ($existing as $schedule) {
            $existingIds[$schedule->employee_id.'|'.$schedule->date->format('Y-m-d')] = $schedule->id;
        }

        $now = now();
        $insert = [];
        $updateIds = [];

        foreach ($employeeIds as $employeeId) {
            foreach ($dates as $date) {
                $key = $employeeId.'|'.$date;

                if (isset($existingIds[$key])) {
                    $updateIds[] = $existingIds[$key];
                } else {
                    $insert[] = [
                        'tenant_id' => $tenantId,
                        'employee_id' => $employeeId,
                        'shift_id' => $validated['shift_id'],
                        'date' => $date,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if ($insert !== []) {
            ShiftSchedule::insert($insert);
        }

        if ($updateIds !== []) {
            ShiftSchedule::whereIn('id', $updateIds)->update(['shift_id' => $validated['shift_id']]);
        }

        $total = count($employeeIds) * $dates->count();

        return back()->with('success', "Shift diterapkan ke {$total} jadwal.");
    }

    /**
     * Copy the previous week's roster onto the currently viewed week, so a
     * recurring schedule can be rolled forward with one click.
     */
    public function copyPreviousWeek(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $tenantId = $request->user()->tenant_id;
        $weekStart = $this->resolveWeekStart($request->input('week_start'));
        $prevStart = $weekStart->copy()->subDays(7);

        $previous = ShiftSchedule::forTenant($tenantId)
            ->whereDate('date', '>=', $prevStart->format('Y-m-d'))
            ->whereDate('date', '<=', $prevStart->copy()->addDays(6)->format('Y-m-d'))
            ->get(['employee_id', 'shift_id', 'date']);

        if ($previous->isEmpty()) {
            return back()->with('error', 'Minggu lalu belum ada jadwal untuk disalin.');
        }

        $existing = ShiftSchedule::forTenant($tenantId)
            ->whereDate('date', '>=', $weekStart->format('Y-m-d'))
            ->whereDate('date', '<=', $weekStart->copy()->addDays(6)->format('Y-m-d'))
            ->get(['id', 'employee_id', 'date']);

        $existingIds = [];
        foreach ($existing as $schedule) {
            $existingIds[$schedule->employee_id.'|'.$schedule->date->format('Y-m-d')] = $schedule->id;
        }

        $now = now();
        $insert = [];

        foreach ($previous as $schedule) {
            $target = Carbon::parse($schedule->date)->addDays(7)->format('Y-m-d');
            $key = $schedule->employee_id.'|'.$target;

            if (isset($existingIds[$key])) {
                ShiftSchedule::whereKey($existingIds[$key])->update(['shift_id' => $schedule->shift_id]);
            } else {
                $insert[] = [
                    'tenant_id' => $tenantId,
                    'employee_id' => $schedule->employee_id,
                    'shift_id' => $schedule->shift_id,
                    'date' => $target,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($insert !== []) {
            ShiftSchedule::insert($insert);
        }

        return back()->with('success', 'Jadwal minggu lalu disalin ke minggu ini.');
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
