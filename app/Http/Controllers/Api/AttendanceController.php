<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSelfie;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee self-service attendance: today status, monthly history, and a single
 * clock endpoint (type = in|out) with GPS + optional selfie.
 */
class AttendanceController extends Controller
{
    use ResolvesApiEmployee;

    public function today(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $record = $this->todayRecord($employee->tenant_id, $employee->id);

        return response()->json(['data' => $this->todayShape($record)]);
    }

    public function history(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $month = $request->query('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();

        $records = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->orderByDesc('date')
            ->get()
            ->map(fn (Attendance $a): array => [
                'id' => $a->id,
                'date' => $a->date instanceof Carbon ? $a->date->toDateString() : $a->date,
                'clock_in' => $a->clock_in_at?->format('H:i'),
                'clock_out' => $a->clock_out_at?->format('H:i'),
                'status' => $a->status,
                'work_minutes' => (int) $a->work_minutes,
            ]);

        return response()->json(['data' => $records, 'meta' => ['month' => $month]]);
    }

    public function clock(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'face_confidence' => ['nullable', 'numeric'],
            'selfie' => ['nullable', 'image', 'max:4096'],
        ]);

        return $data['type'] === 'in'
            ? $this->clockIn($request, $employee, $data)
            : $this->clockOut($employee, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockIn(Request $request, Employee $employee, array $data): JsonResponse
    {
        $attendance = Attendance::firstOrNew([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
        ]);

        if ($attendance->clock_in_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-in hari ini.'], 422);
        }

        $attendance->fill([
            'branch_id' => $employee->branch_id,
            'work_location_id' => $employee->work_location_id,
            'clock_in_at' => now(),
            'clock_in_lat' => $data['latitude'] ?? null,
            'clock_in_lng' => $data['longitude'] ?? null,
            'status' => 'present',
            'location_status' => isset($data['latitude']) ? 'inside' : null,
        ]);
        $attendance->save();

        if ($request->hasFile('selfie')) {
            AttendanceSelfie::create([
                'tenant_id' => $employee->tenant_id,
                'attendance_id' => $attendance->id,
                'employee_id' => $employee->id,
                'file_path' => $request->file('selfie')->store('selfies', 'public'),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'captured_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Clock-in berhasil', 'data' => $this->todayShape($attendance)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockOut(Employee $employee, array $data): JsonResponse
    {
        $attendance = $this->todayRecord($employee->tenant_id, $employee->id);

        if ($attendance === null || $attendance->clock_in_at === null) {
            return response()->json(['message' => 'Anda belum clock-in hari ini.'], 422);
        }

        if ($attendance->clock_out_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-out hari ini.'], 422);
        }

        $attendance->clock_out_at = now();
        $attendance->clock_out_lat = $data['latitude'] ?? null;
        $attendance->clock_out_lng = $data['longitude'] ?? null;
        $attendance->work_minutes = (int) $attendance->clock_in_at->diffInMinutes(now());
        $attendance->save();

        return response()->json(['message' => 'Clock-out berhasil', 'data' => $this->todayShape($attendance)]);
    }

    private function todayRecord(int $tenantId, int $employeeId): ?Attendance
    {
        return Attendance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', now()->toDateString())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function todayShape(?Attendance $a): array
    {
        $nextAction = 'done';
        if ($a === null || $a->clock_in_at === null) {
            $nextAction = 'in';
        } elseif ($a->clock_out_at === null) {
            $nextAction = 'out';
        }

        return [
            'date' => now()->toDateString(),
            'clock_in' => $a?->clock_in_at?->format('H:i'),
            'clock_out' => $a?->clock_out_at?->format('H:i'),
            'next_action' => $nextAction,
            'summary' => [
                'status' => $a?->status,
                'work_minutes' => (int) ($a?->work_minutes ?? 0),
            ],
        ];
    }
}
