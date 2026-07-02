<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\WfhRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Employee self-service work-from-home requests. */
class WfhController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = WfhRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->get(['id', 'start_date', 'end_date', 'reason', 'status'])
            ->map(fn (WfhRequest $w): array => [
                'id' => $w->id,
                'start_date' => $w->start_date instanceof Carbon ? $w->start_date->toDateString() : $w->start_date,
                'end_date' => $w->end_date instanceof Carbon ? $w->end_date->toDateString() : $w->end_date,
                'reason' => $w->reason,
                'status' => $w->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $wfh = WfhRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Pengajuan WFH terkirim', 'data' => ['id' => $wfh->id]], 201);
    }
}
