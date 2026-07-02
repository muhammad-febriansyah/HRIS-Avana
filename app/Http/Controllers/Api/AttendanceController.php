<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSelfie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Employee self-service attendance: clock in/out (GPS + optional selfie) and
 * history. All records are scoped to the authenticated employee.
 */
class AttendanceController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Monthly attendance history (defaults to the current month).
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $month = $request->query('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $records = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('date')
            ->get()
            ->map(fn (Attendance $a): array => $this->row($a));

        return response()->json(['month' => $month, 'data' => $records]);
    }

    /**
     * Today's attendance record (or null if not clocked in yet).
     */
    public function today(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $record = $this->todayRecord($employee->tenant_id, $employee->id);

        return response()->json(['data' => $record === null ? null : $this->row($record)]);
    }

    /**
     * Clock in for today with GPS coordinates and an optional selfie.
     */
    public function clockIn(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'selfie' => ['nullable', 'image', 'max:4096'],
        ]);

        $today = now()->toDateString();

        $attendance = Attendance::firstOrNew([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'date' => $today,
        ]);

        if ($attendance->clock_in_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-in hari ini.'], 422);
        }

        $attendance->fill([
            'branch_id' => $employee->branch_id,
            'work_location_id' => $employee->work_location_id,
            'clock_in_at' => now(),
            'clock_in_lat' => $data['lat'] ?? null,
            'clock_in_lng' => $data['lng'] ?? null,
            'status' => 'present',
            'location_status' => isset($data['lat']) ? 'inside' : null,
        ]);
        $attendance->save();

        if ($request->hasFile('selfie')) {
            $path = $request->file('selfie')->store('selfies', 'public');
            AttendanceSelfie::create([
                'tenant_id' => $employee->tenant_id,
                'attendance_id' => $attendance->id,
                'employee_id' => $employee->id,
                'file_path' => $path,
                'latitude' => $data['lat'] ?? null,
                'longitude' => $data['lng'] ?? null,
                'captured_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Clock-in berhasil', 'data' => $this->row($attendance)]);
    }

    /**
     * Clock out for today.
     */
    public function clockOut(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $attendance = $this->todayRecord($employee->tenant_id, $employee->id);

        if ($attendance === null || $attendance->clock_in_at === null) {
            return response()->json(['message' => 'Anda belum clock-in hari ini.'], 422);
        }

        if ($attendance->clock_out_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-out hari ini.'], 422);
        }

        $attendance->clock_out_at = now();
        $attendance->clock_out_lat = $data['lat'] ?? null;
        $attendance->clock_out_lng = $data['lng'] ?? null;
        $attendance->work_minutes = (int) $attendance->clock_in_at->diffInMinutes(now());
        $attendance->save();

        return response()->json(['message' => 'Clock-out berhasil', 'data' => $this->row($attendance)]);
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
    private function row(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'date' => $a->date instanceof Carbon ? $a->date->toDateString() : $a->date,
            'clock_in_at' => $a->clock_in_at?->toDateTimeString(),
            'clock_out_at' => $a->clock_out_at?->toDateTimeString(),
            'status' => $a->status,
            'late_minutes' => (int) $a->late_minutes,
            'work_minutes' => (int) $a->work_minutes,
            'location_status' => $a->location_status,
        ];
    }
}
