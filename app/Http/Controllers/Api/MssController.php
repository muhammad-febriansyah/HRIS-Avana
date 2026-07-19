<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\Reimbursement;
use App\Models\Settlement;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\WfhRequest;
use App\Services\LeaveAttendanceMarker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manager Self-Service (MSS): the mobile counterpart of the web approval centre.
 * A manager reviews the pending requests routed to them (current_approver_id ==
 * their employee id) and their direct-report roster.
 */
class MssController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Request `type` tag → backing model. Only manager-approvable modules.
     *
     * @var array<string, class-string<Model>>
     */
    private const TYPE_MODELS = [
        'leave' => LeaveRequest::class,
        'lembur' => OvertimeRequest::class,
        'izin' => PermissionRequest::class,
        'wfh' => WfhRequest::class,
        'koreksi' => AttendanceCorrection::class,
        'reimburse' => Reimbursement::class,
        'settlement' => Settlement::class,
    ];

    /**
     * The pending/approved status each type uses. Most requests share the
     * approve/reject vocabulary; a settlement passes two review desks, so its
     * manager step lands on `manager_approved` rather than `approved`.
     *
     * @var array<string, array{pending: string, approved: string}>
     */
    private const TYPE_STATUSES = [
        'settlement' => ['pending' => 'submitted', 'approved' => 'manager_approved'],
    ];

    /**
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'leave' => 'Cuti',
        'lembur' => 'Lembur',
        'izin' => 'Izin',
        'wfh' => 'WFH',
        'koreksi' => 'Koreksi Absen',
        'reimburse' => 'Reimbursement',
        'settlement' => 'Settlement',
    ];

    /**
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    /**
     * Attendance statuses tallied in the team recap → Indonesian label.
     *
     * @var array<string, string>
     */
    private const ATTENDANCE_STATUSES = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'absent' => 'Alpa',
        'leave' => 'Cuti',
        'wfh' => 'WFH',
        'holiday' => 'Libur',
    ];

    /** Pending requests routed to the current manager, newest first. */
    public function approvals(Request $request): JsonResponse
    {
        return $this->listByStatus($this->currentEmployee($request), ['pending'], byDecidedAt: false);
    }

    /** Requests this manager has already approved or rejected, newest decision first. */
    public function history(Request $request): JsonResponse
    {
        return $this->listByStatus($this->currentEmployee($request), ['approved', 'rejected'], byDecidedAt: true);
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function listByStatus(Employee $manager, array $statuses, bool $byDecidedAt): JsonResponse
    {
        $items = collect(self::TYPE_MODELS)
            ->flatMap(fn (string $modelClass, string $type) => $this->itemsForType($type, $modelClass, $manager, $statuses, $byDecidedAt))
            ->sortByDesc('sort_ts')
            ->map(fn (array $item) => collect($item)->except('sort_ts')->all())
            ->values();

        return response()->json(['data' => $items]);
    }

    /** Approve or reject one request identified by its composite key ("leave-12"). */
    public function act(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $manager = $this->currentEmployee($request);
        $model = $this->resolveForManager($key, $manager);

        $this->applyDecision($model, $data['action'], $manager);

        return response()->json([
            'message' => $data['action'] === 'approve' ? 'Permintaan disetujui.' : 'Permintaan ditolak.',
        ]);
    }

    /** Approve or reject many requests at once. */
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $manager = $this->currentEmployee($request);
        $done = 0;

        foreach ($data['ids'] as $key) {
            $model = $this->resolveForManager($key, $manager, abortOnMiss: false);
            if ($model === null) {
                continue;
            }
            $this->applyDecision($model, $data['action'], $manager);
            $done++;
        }

        return response()->json([
            'message' => "$done permintaan diperbarui.",
            'processed' => $done,
        ]);
    }

    /** The manager's direct reports. */
    public function team(Request $request): JsonResponse
    {
        $manager = $this->currentEmployee($request);

        $team = Employee::forTenant($manager->tenant_id)
            ->where('manager_id', $manager->id)
            ->with(['position:id,name', 'department:id,name'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (Employee $e): array => [
                'id' => $e->id,
                'name' => $e->full_name,
                'employee_number' => $e->employee_number,
                'position' => $e->position?->name,
                'department' => $e->department?->name,
                'status' => $e->status,
                'initials' => $this->initials($e->full_name),
                'avatar_color' => $this->avatarColor($e->full_name),
            ]);

        return response()->json(['data' => $team]);
    }

    /** The tenant's active shifts, for the manager to assign from. */
    public function shifts(Request $request): JsonResponse
    {
        $manager = $this->currentEmployee($request);

        $shifts = Shift::forTenant($manager->tenant_id)
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get(['id', 'code', 'name', 'start_time', 'end_time'])
            ->map(fn (Shift $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'start' => $this->shortTime($s->start_time),
                'end' => $this->shortTime($s->end_time),
            ]);

        return response()->json(['data' => $shifts]);
    }

    /**
     * Assign a shift (or an explicit day off when shift_id is null) to a direct
     * report on a date. Idempotent per employee+date — re-assigning replaces.
     */
    public function assignShift(Request $request): JsonResponse
    {
        $manager = $this->currentEmployee($request);

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer'],
        ]);

        $member = Employee::forTenant($manager->tenant_id)
            ->where('manager_id', $manager->id)
            ->find($data['employee_id']);

        abort_if($member === null, 404, 'Anggota tim tidak ditemukan atau bukan bawahan Anda.');

        $shift = null;
        if ($data['shift_id'] !== null) {
            $shift = Shift::forTenant($manager->tenant_id)
                ->where('status', 'active')
                ->find($data['shift_id']);

            abort_if($shift === null, 422, 'Shift tidak valid.');
        }

        $schedule = ShiftSchedule::forTenant($manager->tenant_id)
            ->where('employee_id', $member->id)
            ->whereDate('date', $data['date'])
            ->first()
            ?? new ShiftSchedule([
                'tenant_id' => $manager->tenant_id,
                'employee_id' => $member->id,
                'date' => $data['date'],
            ]);

        $schedule->shift_id = $shift?->id;
        $schedule->save();

        return response()->json([
            'message' => $shift !== null ? 'Shift diatur: '.$shift->name : 'Ditandai libur',
            'data' => [
                'date' => $data['date'],
                'is_off' => $shift === null,
                'shift_name' => $shift?->name,
                'start' => $shift !== null ? $this->shortTime($shift->start_time) : null,
                'end' => $shift !== null ? $this->shortTime($shift->end_time) : null,
            ],
        ]);
    }

    /**
     * Team attendance recap over a period: per-member hadir/telat/alpa/cuti/WFH
     * counts, worked hours, and late minutes, plus a team summary. Defaults to
     * the current month to date; override with ?start=&end= (Y-m-d).
     */
    public function teamAttendance(Request $request): JsonResponse
    {
        $manager = $this->currentEmployee($request);
        [$start, $end] = $this->recapRange($request);

        $members = $this->buildTeamAttendance($manager, $start, $end);

        $summary = ['members' => $members->count()];
        foreach (array_keys(self::ATTENDANCE_STATUSES) as $status) {
            $summary[$status] = (int) $members->sum($status);
        }
        $summary['work_hours'] = round((float) $members->sum('work_hours'), 1);
        $summary['late_minutes'] = (int) $members->sum('late_minutes');

        return response()->json([
            'data' => $members->values(),
            'meta' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'summary' => $summary,
            ],
        ]);
    }

    /** The same team recap as {@see self::teamAttendance()} streamed as a CSV. */
    public function teamAttendanceExport(Request $request): StreamedResponse
    {
        $manager = $this->currentEmployee($request);
        [$start, $end] = $this->recapRange($request);

        $members = $this->buildTeamAttendance($manager, $start, $end);

        $header = array_merge(
            ['Nama', 'No. Karyawan'],
            array_values(self::ATTENDANCE_STATUSES),
            ['Jam Kerja', 'Menit Telat'],
        );

        $filename = 'rekap-absensi-tim-'.$start->toDateString().'-'.$end->toDateString().'.csv';

        return response()->streamDownload(function () use ($members, $header): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($members as $member) {
                $row = [$member['name'], $member['employee_number']];
                foreach (array_keys(self::ATTENDANCE_STATUSES) as $status) {
                    $row[] = $member[$status];
                }
                $row[] = $member['work_hours'];
                $row[] = $member['late_minutes'];

                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolve the recap period from ?start=&end= (Y-m-d), defaulting to the
     * first of the current month through today.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function recapRange(Request $request): array
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $start = isset($data['start']) ? Carbon::parse($data['start'])->startOfDay() : now()->startOfMonth();
        $end = isset($data['end']) ? Carbon::parse($data['end'])->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    /**
     * Per-member attendance tallies for the manager's direct reports in a range.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function buildTeamAttendance(Employee $manager, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $members = Employee::forTenant($manager->tenant_id)
            ->where('manager_id', $manager->id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number']);

        $records = Attendance::forTenant($manager->tenant_id)
            ->whereIn('employee_id', $members->pluck('id'))
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get(['employee_id', 'status', 'work_minutes', 'late_minutes'])
            ->groupBy('employee_id');

        return $members->map(function (Employee $employee) use ($records): array {
            $rows = $records->get($employee->id, collect());

            $counts = [];
            foreach (array_keys(self::ATTENDANCE_STATUSES) as $status) {
                $counts[$status] = $rows->where('status', $status)->count();
            }

            return array_merge([
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'initials' => $this->initials($employee->full_name),
                'avatar_color' => $this->avatarColor($employee->full_name),
            ], $counts, [
                'work_hours' => round((int) $rows->sum('work_minutes') / 60, 1),
                'late_minutes' => (int) $rows->sum('late_minutes'),
            ]);
        });
    }

    /**
     * A single direct report with the context a manager needs before deciding:
     * this month's attendance recap, today's shift, and their pending requests.
     */
    public function member(Request $request, int $employee): JsonResponse
    {
        $manager = $this->currentEmployee($request);

        $member = Employee::forTenant($manager->tenant_id)
            ->where('manager_id', $manager->id)
            ->with(['position:id,name', 'department:id,name'])
            ->find($employee);

        abort_if($member === null, 404, 'Anggota tim tidak ditemukan atau bukan bawahan Anda.');

        return response()->json(['data' => [
            'member' => [
                'id' => $member->id,
                'name' => $member->full_name,
                'employee_number' => $member->employee_number,
                'position' => $member->position?->name,
                'department' => $member->department?->name,
                'status' => $member->status,
                'initials' => $this->initials($member->full_name),
                'avatar_color' => $this->avatarColor($member->full_name),
            ],
            'attendance' => $this->attendanceRecap($member),
            'today_shift' => $this->memberTodayShift($member),
            'pending' => $this->memberPending($member),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attendanceRecap(Employee $member): array
    {
        $rows = Attendance::forTenant($member->tenant_id)
            ->where('employee_id', $member->id)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->get(['status', 'work_minutes']);

        return [
            'month' => now()->format('M Y'),
            'present' => $rows->where('status', 'present')->count(),
            'late' => $rows->where('status', 'late')->count(),
            'absent' => $rows->where('status', 'absent')->count(),
            'work_hours' => round((int) $rows->sum('work_minutes') / 60, 1),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function memberTodayShift(Employee $member): ?array
    {
        $schedule = ShiftSchedule::forTenant($member->tenant_id)
            ->where('employee_id', $member->id)
            ->whereDate('date', now()->toDateString())
            ->with('shift:id,name,start_time,end_time')
            ->first();

        if ($schedule === null) {
            return null;
        }

        $shift = $schedule->shift;

        return [
            'is_off' => $shift === null,
            'shift_name' => $shift?->name,
            'start' => $shift !== null ? $this->shortTime($shift->start_time) : null,
            'end' => $shift !== null ? $this->shortTime($shift->end_time) : null,
        ];
    }

    /**
     * The member's own pending requests across every type (not scoped to the
     * manager as approver — this is context, not an approval queue).
     *
     * @return array<int, array<string, mixed>>
     */
    private function memberPending(Employee $member): array
    {
        $out = [];

        foreach (self::TYPE_MODELS as $type => $modelClass) {
            $query = $modelClass::query()
                ->where('tenant_id', $member->tenant_id)
                ->where('employee_id', $member->id)
                ->where('status', 'pending');

            if ($type === 'leave') {
                $query->with('leaveType:id,name');
            }

            foreach ($query->get() as $m) {
                $out[] = [
                    'id' => "$type-{$m->id}",
                    'type' => $type,
                    'type_label' => self::TYPE_LABELS[$type] ?? $type,
                    'title' => $this->titleFor($m, $type),
                    'detail' => $this->detailFor($m, $type),
                ];
            }
        }

        return $out;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $statuses
     * @return Collection<int, array<string, mixed>>
     */
    private function itemsForType(string $type, string $modelClass, Employee $manager, array $statuses, bool $byDecidedAt)
    {
        $query = $modelClass::query()
            ->where('tenant_id', $manager->tenant_id)
            ->where('current_approver_id', $manager->id)
            ->whereIn('status', $this->statusesFor($type, $statuses))
            ->with('employee:id,full_name,employee_number');

        if ($type === 'leave') {
            $query->with('leaveType:id,name');
        }

        $sortColumn = $byDecidedAt ? 'updated_at' : 'created_at';

        return $query->latest($sortColumn)->get()->map(fn (Model $m): array => [
            'id' => "$type-{$m->id}",
            'type' => $type,
            'type_label' => self::TYPE_LABELS[$type] ?? $type,
            'employee' => $this->shapeEmployee($m),
            'title' => $this->titleFor($m, $type),
            'detail' => $this->detailFor($m, $type),
            'reason' => $m->reason ?? $m->description ?? null,
            'status' => $m->status,
            'requested_at' => $m->created_at?->format('d M Y H:i'),
            'decided_at' => $byDecidedAt ? $m->updated_at?->format('d M Y H:i') : null,
            'sort_ts' => ($byDecidedAt ? $m->updated_at : $m->created_at)?->getTimestamp() ?? 0,
        ]);
    }

    /**
     * Resolve a request by "type-id", scoped to this manager. Aborts with 404
     * unless {@see $abortOnMiss} is false (used by bulk to skip stale ids).
     */
    private function resolveForManager(string $key, Employee $manager, bool $abortOnMiss = true): ?Model
    {
        [$type, $id] = array_pad(explode('-', $key, 2), 2, null);
        $modelClass = self::TYPE_MODELS[$type] ?? null;

        $model = ($modelClass !== null && is_numeric($id))
            ? $modelClass::query()
                ->where('tenant_id', $manager->tenant_id)
                ->where('current_approver_id', $manager->id)
                ->whereIn('status', $this->statusesFor((string) $type, ['pending']))
                ->find((int) $id)
            : null;

        abort_if($abortOnMiss && $model === null, 404, 'Permintaan tidak ditemukan atau bukan tanggung jawab Anda.');

        return $model;
    }

    /**
     * Translate the shared pending/approved statuses into the vocabulary the
     * given type actually stores.
     *
     * @param  array<int, string>  $statuses
     * @return array<int, string>
     */
    private function statusesFor(string $type, array $statuses): array
    {
        $map = self::TYPE_STATUSES[$type] ?? null;

        if ($map === null) {
            return $statuses;
        }

        return array_map(fn (string $status): string => $map[$status] ?? $status, $statuses);
    }

    private function applyDecision(Model $model, string $action, Employee $manager): void
    {
        $approved = $action === 'approve';

        if ($model instanceof Settlement) {
            $model->update([
                'status' => $approved
                    ? Settlement::STATUS_MANAGER_APPROVED
                    : Settlement::STATUS_REJECTED,
                'manager_approved_by' => $approved ? $manager->user_id : null,
                'manager_approved_at' => $approved ? now() : null,
                'rejected_by' => $approved ? null : $manager->user_id,
                'rejected_at' => $approved ? null : now(),
                // Off the manager's desk either way: on to Finance, or back
                // to the employee.
                'current_approver_id' => null,
            ]);

            return;
        }

        $model->update(['status' => $approved ? 'approved' : 'rejected']);

        if ($model instanceof AttendanceCorrection) {
            $model->update(['approver_id' => $manager->user_id]);

            if ($approved) {
                $this->applyCorrection($model);
            }

            return;
        }

        if ($model instanceof Reimbursement) {
            $model->update([
                'approver_id' => $manager->user_id,
                'approved_at' => $approved ? now() : null,
            ]);

            return;
        }

        if ($approved && $model instanceof LeaveRequest) {
            $this->decrementLeaveBalance($model);
            LeaveAttendanceMarker::mark($model);
        }
    }

    /**
     * Write an approved correction to the attendance record: set the requested
     * clock-in / clock-out on that day, recompute worked minutes when both are
     * present, and link the record back to the request for the audit trail. The
     * attendance row is created when the employee had no record for that day
     * (the "forgot to clock in entirely" case).
     */
    private function applyCorrection(AttendanceCorrection $correction): void
    {
        $date = $correction->date;

        $attendance = $correction->attendance ?? Attendance::firstOrNew([
            'tenant_id' => $correction->tenant_id,
            'employee_id' => $correction->employee_id,
            'date' => $date->toDateString(),
        ]);

        if ($correction->requested_clock_in !== null) {
            $attendance->clock_in_at = $date->copy()->setTimeFromTimeString($correction->requested_clock_in);
        }

        if ($correction->requested_clock_out !== null) {
            $attendance->clock_out_at = $date->copy()->setTimeFromTimeString($correction->requested_clock_out);
        }

        if ($attendance->branch_id === null) {
            $attendance->branch_id = $correction->employee?->branch_id;
        }
        $attendance->status = 'present';

        if ($attendance->clock_in_at !== null && $attendance->clock_out_at !== null) {
            $attendance->work_minutes = (int) $attendance->clock_in_at->diffInMinutes($attendance->clock_out_at);
        }

        $attendance->save();

        $correction->update(['attendance_id' => $attendance->id]);
    }

    private function decrementLeaveBalance(LeaveRequest $leave): void
    {
        $balance = LeaveBalance::query()
            ->where('employee_id', $leave->employee_id)
            ->where('leave_type_id', $leave->leave_type_id)
            ->where('year', $leave->start_date->year)
            ->first();

        $balance?->update([
            'used' => $balance->used + $leave->total_days,
            'remaining' => max(0, $balance->remaining - $leave->total_days),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shapeEmployee(Model $model): ?array
    {
        $employee = $model->employee;

        if ($employee === null) {
            return null;
        }

        return [
            'name' => $employee->full_name,
            'employee_number' => $employee->employee_number,
            'initials' => $this->initials($employee->full_name),
            'avatar_color' => $this->avatarColor($employee->full_name),
        ];
    }

    private function titleFor(Model $model, string $type): string
    {
        return match ($type) {
            'leave' => $model->leaveType?->name ?? 'Cuti',
            'lembur' => 'Lembur '.(float) $model->hours.' jam',
            'izin' => $model->type === 'keluar_kantor' ? 'Keluar Kantor' : 'Izin Jam',
            'wfh' => 'Work From Home',
            'koreksi' => 'Koreksi Absen',
            'reimburse' => $model->title ?: 'Reimbursement',
            'settlement' => $model->title ?: 'Settlement',
            default => ucfirst($type),
        };
    }

    private function detailFor(Model $model, string $type): string
    {
        return match ($type) {
            'leave', 'wfh' => $this->dateRange($model),
            'lembur' => $model->date?->format('d M Y') ?? '—',
            'izin' => $this->izinDetail($model),
            'koreksi' => $this->koreksiDetail($model),
            'reimburse' => 'Rp '.number_format((float) $model->amount, 0, ',', '.'),
            'settlement' => 'Rp '.number_format((float) $model->total, 0, ',', '.'),
            default => '—',
        };
    }

    private function koreksiDetail(Model $model): string
    {
        $date = $model->date?->format('d M Y') ?? '—';
        $in = $this->shortTime($model->requested_clock_in);
        $out = $this->shortTime($model->requested_clock_out);

        $parts = [];
        if ($in !== null) {
            $parts[] = "Masuk $in";
        }
        if ($out !== null) {
            $parts[] = "Pulang $out";
        }

        return $parts === [] ? $date : "$date · ".implode(', ', $parts);
    }

    private function dateRange(Model $model): string
    {
        $start = $model->start_date;
        $end = $model->end_date;

        if ($start === null) {
            return '—';
        }

        if ($end === null || $end->isSameDay($start)) {
            return $start->format('d M Y');
        }

        return $start->format('d M Y').' – '.$end->format('d M Y');
    }

    private function izinDetail(Model $model): string
    {
        $date = $model->date?->format('d M Y') ?? '—';
        $start = $this->shortTime($model->start_time);
        $end = $this->shortTime($model->end_time);

        return ($start !== null && $end !== null) ? "$date · {$start}–{$end}" : $date;
    }

    private function shortTime(?string $time): ?string
    {
        return ($time === null || $time === '') ? null : substr($time, 0, 5);
    }

    private function initials(?string $fullName): string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $fullName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $w): string => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    private function avatarColor(?string $fullName): string
    {
        return self::AVATAR_PALETTE[crc32((string) $fullName) % count(self::AVATAR_PALETTE)];
    }
}
