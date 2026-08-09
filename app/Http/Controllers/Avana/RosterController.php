<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\RosterPattern;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Services\AttendanceFinalizer;
use App\Support\Roster;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'patterns' => RosterPattern::forTenant($tenantId)
                ->where('status', 'active')
                ->with('steps.shift:id,code')
                ->orderBy('name')
                ->get()
                ->map(fn (RosterPattern $pattern): array => [
                    'id' => $pattern->id,
                    'name' => $pattern->name,
                    'industry' => $pattern->industry,
                    'summary' => $pattern->summary(),
                    'cycle_days' => $pattern->cycleDays(),
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
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            // Null marks the day off — the same thing the mobile app can
            // already record, and what "Jadwal Saya" renders as Libur.
            'shift_id' => ['nullable', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
        ]);

        $this->assertShiftRunsOn($tenantId, $validated['shift_id'] ?? null, $validated['date']);

        Roster::assign(
            $tenantId,
            (int) $validated['employee_id'],
            $validated['date'],
            $validated['shift_id'] ?? null,
        );
        AttendanceFinalizer::recalculateRange(
            $tenantId,
            [(int) $validated['employee_id']],
            $validated['date'],
            $validated['date'],
        );

        return back()->with('success', $validated['shift_id'] === null
            ? 'Ditandai libur'
            : 'Jadwal shift disimpan');
    }

    /**
     * Refuse to roster a shift onto a day it does not run.
     *
     * A shift can name the days it operates; scheduling it outside them makes
     * a roster nobody can work and an attendance record nobody can satisfy.
     * A shift that names no days runs every day.
     */
    private function assertShiftRunsOn(int $tenantId, ?int $shiftId, string $date): void
    {
        if ($shiftId === null) {
            return;
        }

        $shift = Shift::forTenant($tenantId)->find($shiftId);

        if ($shift === null || Roster::runsOn($shift, $date)) {
            return;
        }

        throw ValidationException::withMessages([
            'shift_id' => sprintf(
                '%s hanya berjalan pada hari %s.',
                $shift->name,
                implode(', ', Roster::dayNames($shift)),
            ),
        ]);
    }

    /**
     * Bulk-assign one shift to many employees across many dates in a single
     * request — the "isi cepat" workflow for large headcounts. When
     * `employee_ids` is omitted it targets every active employee in the tenant.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

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

        // Dates the shift does not run on are dropped rather than rostered —
        // and counted, so a partial fill never reads as a complete one.
        $shift = Shift::forTenant($tenantId)->find($validated['shift_id']);
        $requested = $dates->count();

        if ($shift !== null) {
            $dates = $dates->filter(fn (string $date): bool => Roster::runsOn($shift, $date))->values();
        }

        $skipped = $requested - $dates->count();

        if ($dates->isEmpty()) {
            return back()->with('error', sprintf(
                '%s tidak berjalan pada tanggal yang dipilih (hanya hari %s).',
                $shift?->name ?? 'Shift',
                implode(', ', $shift !== null ? Roster::dayNames($shift) : []),
            ));
        }

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

        AttendanceFinalizer::recalculateRange(
            $tenantId,
            array_map('intval', $employeeIds),
            $dates->min(),
            $dates->max(),
        );

        $total = count($employeeIds) * $dates->count();

        $message = "Shift diterapkan ke {$total} jadwal.";

        if ($skipped > 0) {
            $message .= " {$skipped} tanggal dilewati karena shift tidak berjalan pada hari itu.";
        }

        return back()->with('success', $message);
    }

    /**
     * Copy the previous week's roster onto the currently viewed week, so a
     * recurring schedule can be rolled forward with one click.
     */
    public function copyPreviousWeek(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

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
        $skipped = 0;
        $shifts = Shift::forTenant($tenantId)->get()->keyBy('id');

        foreach ($previous as $schedule) {
            $target = Carbon::parse($schedule->date)->addDays(7)->format('Y-m-d');
            $key = $schedule->employee_id.'|'.$target;

            // Copying must obey the same rule as assigning by hand, or a shift
            // that only runs on weekdays walks onto a weekend a week later.
            $shift = $schedule->shift_id !== null ? $shifts->get($schedule->shift_id) : null;

            if ($shift !== null && ! Roster::runsOn($shift, $target)) {
                $skipped++;

                continue;
            }

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

        AttendanceFinalizer::recalculateRange(
            $tenantId,
            $previous->pluck('employee_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            $weekStart->format('Y-m-d'),
            $weekStart->copy()->addDays(6)->format('Y-m-d'),
        );

        $message = 'Jadwal minggu lalu disalin ke minggu ini.';

        if ($skipped > 0) {
            $message .= " {$skipped} jadwal dilewati karena shift tidak berjalan pada hari itu.";
        }

        return back()->with('success', $message);
    }

    /**
     * Fill the roster for a set of employees from a rotation pattern.
     *
     * Everyone starts the cycle on the same date, so a whole crew rotating
     * together stays together. Someone who should start mid-cycle gets their
     * own run with an earlier start date.
     */
    public function applyPattern(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'pattern_id' => ['required', Rule::exists('roster_patterns', 'id')->where('tenant_id', $tenantId)],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [
            'employee_ids.required' => 'Pilih minimal satu karyawan.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        // A year of roster per run is already far more than anyone schedules at
        // once, and it keeps one careless date from writing a decade of rows.
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages([
                'end_date' => 'Rentang maksimal 1 tahun sekali terapkan.',
            ]);
        }

        $pattern = RosterPattern::forTenant($tenantId)->with('steps')->findOrFail($data['pattern_id']);

        $assigned = 0;
        $skipped = 0;

        DB::transaction(function () use ($pattern, $data, $start, $end, &$assigned, &$skipped): void {
            foreach ($data['employee_ids'] as $employeeId) {
                $result = Roster::applyPattern($pattern, (int) $employeeId, $start, $end);
                $assigned += $result['assigned'];
                $skipped += $result['skipped'];
            }
        });

        AttendanceFinalizer::recalculateRange(
            $tenantId,
            array_map('intval', $data['employee_ids']),
            $start->toDateString(),
            $end->toDateString(),
        );

        $message = "Pola {$pattern->name} diterapkan — {$assigned} jadwal dibuat.";

        if ($skipped > 0) {
            $message .= " {$skipped} tanggal dilewati karena shift tidak berjalan pada hari itu.";
        }

        return back()->with('success', $message);
    }

    /**
     * Remove a shift assignment from the roster.
     */
    public function destroy(Request $request, ShiftSchedule $schedule): RedirectResponse
    {
        $this->authorize('delete', Attendance::class);

        abort_if((int) $schedule->tenant_id !== (int) $request->user()->tenant_id, 404);

        $tenantId = (int) $schedule->tenant_id;
        $employeeId = (int) $schedule->employee_id;
        $date = $schedule->date->toDateString();

        $schedule->delete();
        AttendanceFinalizer::recalculateRange($tenantId, [$employeeId], $date, $date);

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
