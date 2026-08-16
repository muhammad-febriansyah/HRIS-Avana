<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\ApprovalEngine;
use App\Services\LeaveApproval;
use App\Services\LeaveQuota;
use App\Support\RequestDateClash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Cuti Saya" — the employee's own leave balances and requests, mirroring the
 * /api/v1/me/leave endpoints the mobile app uses.
 */
class EssLeaveController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Balances for the year plus the employee's own request history.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);
        $year = now()->year;

        $pendingByType = LeaveRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'pending')
            ->whereYear('start_date', $year)
            ->selectRaw('leave_type_id, SUM(total_days) as days')
            ->groupBy('leave_type_id')
            ->pluck('days', 'leave_type_id');

        $balances = LeaveBalance::forTenant($employee->tenant_id)
            ->forLiveTypes()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->with('leaveType:id,name,code')
            ->get()
            ->map(fn (LeaveBalance $balance): array => [
                'leave_type_id' => (int) $balance->leave_type_id,
                'leave_type' => $balance->leaveType?->name,
                'code' => $balance->leaveType?->code,
                'entitled' => (float) $balance->quota,
                'used' => (float) $balance->used,
                'pending' => (float) ($pendingByType[$balance->leave_type_id] ?? 0),
                'available' => (float) $balance->remaining,
            ])->values();

        $requests = LeaveRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('leaveType:id,name')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (LeaveRequest $leave): array => [
                'id' => $leave->id,
                'leave_type' => $leave->leaveType?->name,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'total_days' => (float) $leave->total_days,
                'reason' => $leave->reason,
                'status' => $leave->status,
            ])->values();

        return Inertia::render('avana/saya/cuti', [
            'year' => $year,
            'balances' => $balances,
            'requests' => $requests,
            'leaveTypes' => LeaveType::selectableTree($employee->tenant_id),
        ]);
    }

    /**
     * Submit a leave request for the signed-in employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('tenant_id', $employee->tenant_id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $totalDays = $start->diffInDays($end) + 1;

        $clash = RequestDateClash::check(
            (int) $employee->tenant_id,
            (int) $employee->id,
            $start->toDateString(),
            $end->toDateString(),
        );

        if ($clash !== null) {
            return back()->withErrors(['start_date' => $clash]);
        }

        $type = LeaveType::forTenant($employee->tenant_id)
            ->with('parent')
            ->findOrFail($data['leave_type_id']);

        $message = LeaveQuota::check($employee->id, $type, (float) $totalDays, $start->year);

        if ($message !== null) {
            return back()->withErrors(['leave_type_id' => $message]);
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

        // A top approver (director) has nobody above them, so their own request
        // is settled on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            LeaveApproval::finalize($leave);

            return back()->with('success', 'Pengajuan cuti langsung disetujui (approver puncak)');
        }

        ApprovalEngine::start($leave, $employee);

        return back()->with('success', 'Pengajuan cuti terkirim');
    }
}
