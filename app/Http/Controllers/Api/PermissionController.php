<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Employee self-service hourly permission (izin keluar) requests. */
class PermissionController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = PermissionRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('date')
            ->get(['id', 'date', 'type', 'start_time', 'end_time', 'reason', 'status'])
            ->map(fn (PermissionRequest $p): array => [
                'id' => $p->id,
                'date' => $p->date instanceof Carbon ? $p->date->toDateString() : $p->date,
                'type' => $p->type,
                'start_time' => $p->start_time,
                'end_time' => $p->end_time,
                'reason' => $p->reason,
                'status' => $p->status,
            ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'max:50'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $permission = PermissionRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'type' => $data['type'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Pengajuan izin terkirim', 'data' => ['id' => $permission->id]], 201);
    }
}
