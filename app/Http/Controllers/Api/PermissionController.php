<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\PermissionRequest;
use App\Services\ApprovalEngine;
use Closure;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Employee self-service hourly permission (izin keluar) requests. */
class PermissionController extends Controller
{
    use ResolvesApiEmployee;

    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = PermissionRequest::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->get(['id', 'start_date', 'end_date', 'type', 'start_time', 'end_time', 'reason', 'status'])
            ->map(fn (PermissionRequest $p): array => [
                'id' => $p->id,
                'start_date' => self::dateString($p->start_date),
                'end_date' => self::dateString($p->end_date),
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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', 'string', 'max:50'],
            // Times narrow a single-day izin to part of that day (e.g. "keluar
            // kantor 09:00-11:00"). A multi-day izin covers whole days, so a
            // time there is rejected rather than stored and silently ignored.
            'start_time' => ['nullable', 'date_format:H:i', self::singleDayOnly($request)],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time', self::singleDayOnly($request)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $permission = PermissionRequest::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'type' => $data['type'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
            'current_approver_id' => $employee->manager_id,
            'status' => 'pending',
        ]);

        // Route through the configured approval workflow when one is active.
        ApprovalEngine::start($permission, $employee);

        return response()->json(['message' => 'Pengajuan izin terkirim', 'data' => ['id' => $permission->id]], 201);
    }

    /**
     * Reject a clock time unless the izin sits on one single day.
     */
    private static function singleDayOnly(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            if ($request->input('start_date') !== $request->input('end_date')) {
                $fail('Jam hanya berlaku untuk izin satu hari.');
            }
        };
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     *
     * Matched on DateTimeInterface, not Illuminate\Support\Carbon: the app runs
     * Date::use(CarbonImmutable::class), and CarbonImmutable does not extend the
     * Illuminate class — so a narrower check silently passes the raw datetime
     * string through.
     */
    private static function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
