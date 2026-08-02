<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftSwap;
use App\Support\Roster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Employee self-service shift-swap requests with a colleague. */
class ShiftSwapController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = ShiftSwap::forTenant($employee->tenant_id)
            ->where(fn ($query) => $query
                ->where('requester_id', $employee->id)
                ->orWhere('target_id', $employee->id))
            ->with([
                'requester:id,full_name',
                'target:id,full_name',
                'requesterShift:id,name',
                'targetShift:id,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShiftSwap $swap): array => [
                'id' => $swap->id,
                'date' => $swap->date instanceof Carbon ? $swap->date->toDateString() : $swap->date,
                'requester' => $swap->requester?->full_name,
                'target' => $swap->target?->full_name,
                'direction' => $swap->requester_id === $employee->id ? 'outgoing' : 'incoming',
                'requester_shift' => $swap->requesterShift?->name ?? 'Libur',
                'target_shift' => $swap->targetShift?->name ?? 'Libur',
                'reason' => $swap->reason,
                'status' => $swap->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'target_id' => [
                'required',
                Rule::exists('employees', 'id')->where('tenant_id', $employee->tenant_id),
                Rule::notIn([$employee->id]),
            ],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'target_id.not_in' => 'Tidak bisa tukar shift dengan diri sendiri.',
            'date.after_or_equal' => 'Tidak bisa menukar shift yang sudah lewat.',
        ]);

        $tenantId = (int) $employee->tenant_id;
        $targetId = (int) $data['target_id'];

        // Trading a day nobody is rostered on trades nothing: an approval would
        // report success and leave both schedules exactly as they were.
        $requesterSchedule = Roster::scheduleFor($tenantId, (int) $employee->id, $data['date']);
        $targetSchedule = Roster::scheduleFor($tenantId, $targetId, $data['date']);

        if ($requesterSchedule === null && $targetSchedule === null) {
            return response()->json([
                'message' => 'Belum ada jadwal untuk tanggal itu, jadi tidak ada yang bisa ditukar.',
            ], 422);
        }

        $duplicate = ShiftSwap::forTenant($tenantId)
            ->where('status', 'pending')
            ->whereDate('date', $data['date'])
            ->where(fn ($query) => $query
                ->where(fn ($pair) => $pair->where('requester_id', $employee->id)->where('target_id', $targetId))
                ->orWhere(fn ($pair) => $pair->where('requester_id', $targetId)->where('target_id', $employee->id)))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Sudah ada pengajuan tukar shift yang menunggu untuk tanggal itu.',
            ], 422);
        }

        // The shifts are recorded now so the approver sees what was asked for.
        // Without them the web approval screen shows a blank trade, and the
        // request carries no record of the schedule the two agreed on.
        $swap = ShiftSwap::create([
            'tenant_id' => $tenantId,
            'requester_id' => $employee->id,
            'target_id' => $targetId,
            'date' => $data['date'],
            'requester_shift_id' => $requesterSchedule?->shift_id,
            'target_shift_id' => $targetSchedule?->shift_id,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Permintaan tukar shift terkirim', 'data' => ['id' => $swap->id]], 201);
    }

    /**
     * Colleagues the employee can request a swap with (same tenant).
     */
    public function colleagues(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = Employee::forTenant($employee->tenant_id)
            ->where('id', '!=', $employee->id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $colleague): array => [
                'id' => $colleague->id,
                'name' => $colleague->full_name,
                'employee_number' => $colleague->employee_number,
            ]);

        return response()->json(['data' => $data]);
    }
}
