<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalEngine;
use App\Services\LeaveApproval;
use App\Services\LeaveQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Employee self-service leave: balances, types, own requests, submit. */
class LeaveController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * @var array<int, string>
     */
    private const AVATAR_PALETTE = [
        '#0ea5e9', '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
        '#f97316', '#f59e0b', '#10b981', '#14b8a6', '#3b82f6',
    ];

    public function balances(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $year = (int) $request->query('year', (string) now()->year);

        // Days locked up in still-pending requests, per leave type, this year.
        $pendingByType = LeaveRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->selectRaw('leave_type_id, SUM(total_days) as days')
            ->groupBy('leave_type_id')
            ->pluck('days', 'leave_type_id');

        $data = LeaveBalance::forTenant($employee->tenant_id)
            ->forLiveTypes()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType:id,name,code')
            ->get()
            ->map(fn (LeaveBalance $b): array => [
                'leave_type_id' => (int) $b->leave_type_id,
                'leave_type' => $b->leaveType?->name,
                'code' => $b->leaveType?->code,
                'year' => (int) $b->year,
                'entitled' => (float) $b->quota,
                'used' => (float) $b->used,
                'pending' => (float) ($pendingByType[$b->leave_type_id] ?? 0),
                'available' => (float) $b->remaining,
            ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Team leave calendar: colleagues in the caller's department who are on
     * approved leave overlapping the period (defaults to the current month).
     */
    public function calendar(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $params = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $start = isset($params['start']) ? Carbon::parse($params['start'])->startOfDay() : now()->startOfMonth();
        $end = isset($params['end']) ? Carbon::parse($params['end'])->endOfDay() : now()->endOfMonth();

        $colleagues = Employee::forTenant($employee->tenant_id)
            ->where('status', 'active')
            ->when($employee->department_id, fn ($query, $id) => $query->where('department_id', $id))
            ->pluck('id');

        $entries = LeaveRequest::forTenant($employee->tenant_id)
            ->whereIn('employee_id', $colleagues)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with(['employee:id,full_name', 'leaveType:id,name'])
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $r): array => [
                'id' => $r->id,
                'employee' => [
                    'id' => $r->employee_id,
                    'name' => $r->employee?->full_name,
                    'initials' => $this->initials($r->employee?->full_name),
                    'avatar_color' => $this->avatarColor($r->employee?->full_name),
                ],
                'is_me' => $r->employee_id === $employee->id,
                'leave_type' => $r->leaveType?->name,
                'start_date' => $r->start_date instanceof Carbon ? $r->start_date->toDateString() : $r->start_date,
                'end_date' => $r->end_date instanceof Carbon ? $r->end_date->toDateString() : $r->end_date,
                'total_days' => (int) $r->total_days,
            ]);

        return response()->json([
            'data' => $entries,
            'meta' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
        ]);
    }

    public function types(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        return response()->json([
            'data' => LeaveType::selectableTree($employee->tenant_id),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = LeaveRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('leaveType:id,name')
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (LeaveRequest $r): array => [
                'id' => $r->id,
                'leave_type' => $r->leaveType?->name,
                'start_date' => $r->start_date instanceof Carbon ? $r->start_date->toDateString() : $r->start_date,
                'end_date' => $r->end_date instanceof Carbon ? $r->end_date->toDateString() : $r->end_date,
                'total_days' => (int) $r->total_days,
                'reason' => $r->reason,
                'status' => $r->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('tenant_id', $employee->tenant_id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = $start->diffInDays($end) + 1;

        $type = LeaveType::forTenant($employee->tenant_id)
            ->with('parent')
            ->findOrFail($data['leave_type_id']);

        $message = LeaveQuota::check($employee->id, $type, (float) $totalDays, $start->year);

        if ($message !== null) {
            return response()->json(['message' => $message], 422);
        }

        $leave = LeaveRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $totalDays,
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        // A top approver (director) has no manager above them, so their own
        // request is approved on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            LeaveApproval::finalize($leave);

            return response()->json([
                'message' => 'Pengajuan cuti langsung disetujui (approver puncak)',
                'data' => ['id' => $leave->id, 'status' => 'approved'],
            ], 201);
        }

        // Route through the configured approval workflow when one is active;
        // otherwise the request keeps its manager_id routing.
        ApprovalEngine::start($leave, $employee);

        return response()->json(['message' => 'Pengajuan cuti terkirim', 'data' => ['id' => $leave->id]], 201);
    }

    private function initials(?string $fullName): string
    {
        $initials = collect(preg_split('/\s+/', trim((string) $fullName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }

    private function avatarColor(?string $fullName): string
    {
        return self::AVATAR_PALETTE[crc32((string) $fullName) % count(self::AVATAR_PALETTE)];
    }
}
