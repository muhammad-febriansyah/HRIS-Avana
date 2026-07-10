<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\WfhRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
        'reimburse' => Claim::class,
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
    ];

    /**
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
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
            ->whereIn('status', $statuses)
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
                ->where('status', 'pending')
                ->find((int) $id)
            : null;

        abort_if($abortOnMiss && $model === null, 404, 'Permintaan tidak ditemukan atau bukan tanggung jawab Anda.');

        return $model;
    }

    private function applyDecision(Model $model, string $action, Employee $manager): void
    {
        $approved = $action === 'approve';
        $model->update(['status' => $approved ? 'approved' : 'rejected']);

        if ($model instanceof AttendanceCorrection) {
            $model->update(['approver_id' => $manager->user_id]);

            if ($approved) {
                $this->applyCorrection($model);
            }

            return;
        }

        if ($model instanceof Claim) {
            $model->update([
                'approver_id' => $manager->user_id,
                'approved_at' => $approved ? now() : null,
            ]);

            return;
        }

        if ($approved && $model instanceof LeaveRequest) {
            $this->decrementLeaveBalance($model);
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
