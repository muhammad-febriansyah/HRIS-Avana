<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ShiftSwap;
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
            ->with(['requester:id,full_name', 'target:id,full_name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShiftSwap $swap): array => [
                'id' => $swap->id,
                'date' => $swap->date instanceof Carbon ? $swap->date->toDateString() : $swap->date,
                'requester' => $swap->requester?->full_name,
                'target' => $swap->target?->full_name,
                'direction' => $swap->requester_id === $employee->id ? 'outgoing' : 'incoming',
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
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'target_id.not_in' => 'Tidak bisa tukar shift dengan diri sendiri.',
        ]);

        $swap = ShiftSwap::create([
            'tenant_id' => $employee->tenant_id,
            'requester_id' => $employee->id,
            'target_id' => $data['target_id'],
            'date' => $data['date'],
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
