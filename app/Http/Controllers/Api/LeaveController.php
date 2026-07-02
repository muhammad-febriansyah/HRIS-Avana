<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Employee self-service leave: balances, types, own requests, submit. */
class LeaveController extends Controller
{
    use ResolvesApiEmployee;

    public function balances(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $year = (int) $request->query('year', (string) now()->year);

        $data = LeaveBalance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType:id,name')
            ->get()
            ->map(fn (LeaveBalance $b): array => [
                'leave_type' => $b->leaveType?->name,
                'year' => (int) $b->year,
                'entitled' => (float) $b->quota,
                'used' => (float) $b->used,
                'pending' => 0.0,
                'available' => (float) $b->remaining,
            ]);

        return response()->json(['data' => $data]);
    }

    public function types(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = LeaveType::forTenant($employee->tenant_id)
            ->where('status', 'active')
            ->get(['id', 'code', 'name', 'default_quota', 'requires_attachment']);

        return response()->json(['data' => $data]);
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

        $type = LeaveType::forTenant($employee->tenant_id)->findOrFail($data['leave_type_id']);

        $balance = LeaveBalance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $start->year)
            ->first();

        $remaining = $balance !== null ? (int) $balance->remaining : (int) $type->default_quota;

        if (! $type->allow_negative && $totalDays > $remaining) {
            return response()->json([
                'message' => 'Saldo cuti tidak mencukupi (sisa '.$remaining.' hari).',
            ], 422);
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

        return response()->json(['message' => 'Pengajuan cuti terkirim', 'data' => ['id' => $leave->id]], 201);
    }
}
