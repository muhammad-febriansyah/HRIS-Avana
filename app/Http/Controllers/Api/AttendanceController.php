<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSelfie;
use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\WorkLocation;
use App\Support\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            'is_mock_location' => ['nullable', 'boolean'],
            'is_rooted' => ['nullable', 'boolean'],
            // Original clock time for entries queued offline and synced later.
            'clocked_at' => ['nullable', 'date', 'after_or_equal:'.now()->subDays(7)->toDateTimeString()],
            'face_embedding' => ['nullable', 'array', 'min:64', 'max:1024'],
            'face_embedding.*' => ['numeric'],
            'selfie' => ['nullable', 'image', 'max:4096'],
        ]);

        // Resolve the effective clock time; never allow a future timestamp.
        $clockedAt = isset($data['clocked_at']) ? Carbon::parse($data['clocked_at']) : now();
        if ($clockedAt->isFuture()) {
            $clockedAt = now();
        }
        $data['clocked_at'] = $clockedAt;

        if ($request->boolean('is_mock_location')) {
            return response()->json([
                'message' => 'Terdeteksi lokasi palsu (Fake GPS). Nonaktifkan mock location lalu coba lagi.',
            ], 422);
        }

        if ($request->boolean('is_rooted')) {
            return response()->json([
                'message' => 'Perangkat terdeteksi di-root/jailbreak. Absen tidak diizinkan dari perangkat ini.',
            ], 422);
        }

        // Face verification — only enforced once the employee has enrolled a face.
        $data['face_confidence'] = null;
        $enrolled = EmployeeFaceEmbedding::where('employee_id', $employee->id)->first();

        if ($enrolled !== null) {
            $submitted = $data['face_embedding'] ?? null;

            if (! is_array($submitted) || $submitted === []) {
                return response()->json([
                    'message' => 'Verifikasi wajah diperlukan. Aktifkan kamera lalu coba lagi.',
                ], 422);
            }

            $score = FaceMatcher::cosine($enrolled->embedding, $submitted);

            if ($score < FaceMatcher::THRESHOLD) {
                return response()->json([
                    'message' => 'Wajah tidak cocok dengan data terdaftar. Coba lagi.',
                ], 422);
            }

            $data['face_confidence'] = round($score, 4);
        }

        return $data['type'] === 'in'
            ? $this->clockIn($request, $employee, $data)
            : $this->clockOut($employee, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockIn(Request $request, Employee $employee, array $data): JsonResponse
    {
        $clockedAt = $data['clocked_at'];

        $attendance = Attendance::firstOrNew([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'date' => $clockedAt->toDateString(),
        ]);

        if ($attendance->clock_in_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-in hari ini.'], 422);
        }

        $geofence = $this->geofenceCheck($employee, $data);

        if ($geofence instanceof JsonResponse) {
            return $geofence;
        }

        $attendance->fill([
            'branch_id' => $employee->branch_id,
            'work_location_id' => $geofence->id,
            'clock_in_at' => $clockedAt,
            'clock_in_lat' => $data['latitude'] ?? null,
            'clock_in_lng' => $data['longitude'] ?? null,
            'status' => 'present',
            'location_status' => 'inside',
            'face_confidence' => $data['face_confidence'] ?? null,
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
                'captured_at' => $clockedAt,
            ]);
        }

        return response()->json(['message' => 'Clock-in berhasil', 'data' => $this->todayShape($attendance)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockOut(Employee $employee, array $data): JsonResponse
    {
        $clockedAt = $data['clocked_at'];

        $attendance = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $clockedAt->toDateString())
            ->first();

        if ($attendance === null || $attendance->clock_in_at === null) {
            return response()->json(['message' => 'Anda belum clock-in hari ini.'], 422);
        }

        if ($attendance->clock_out_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-out hari ini.'], 422);
        }

        $geofence = $this->geofenceCheck($employee, $data);

        if ($geofence instanceof JsonResponse) {
            return $geofence;
        }

        $attendance->clock_out_at = $clockedAt;
        $attendance->clock_out_lat = $data['latitude'] ?? null;
        $attendance->clock_out_lng = $data['longitude'] ?? null;
        $attendance->work_minutes = (int) $attendance->clock_in_at->diffInMinutes($clockedAt);
        if (($data['face_confidence'] ?? null) !== null) {
            $attendance->face_confidence = $data['face_confidence'];
        }
        $attendance->save();

        return response()->json(['message' => 'Clock-out berhasil', 'data' => $this->todayShape($attendance)]);
    }

    /**
     * Enforce the work-location geofence for a clock action. Returns the matched
     * WorkLocation when the caller is inside an allowed area, or a 422
     * JsonResponse to reject.
     *
     * Rejected when there is no allowed location, when GPS is missing, or when
     * the caller is outside the nearest allowed location's radius.
     *
     * @param  array<string, mixed>  $data
     */
    private function geofenceCheck(Employee $employee, array $data): WorkLocation|JsonResponse
    {
        $locations = $this->allowedWorkLocations($employee);

        if ($locations->isEmpty()) {
            return response()->json([
                'message' => 'Lokasi kerja belum diatur admin. Hubungi HR.',
            ], 422);
        }

        if (! isset($data['latitude'], $data['longitude'])) {
            return response()->json([
                'message' => 'Lokasi GPS tidak terdeteksi. Aktifkan izin lokasi lalu coba lagi.',
            ], 422);
        }

        $latitude = (float) $data['latitude'];
        $longitude = (float) $data['longitude'];

        $nearest = null;
        $nearestDistance = null;

        foreach ($locations as $location) {
            $distance = $location->distanceMeters($latitude, $longitude);

            if ($distance === null) {
                continue;
            }

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $location;
            }
        }

        if ($nearest === null) {
            return response()->json([
                'message' => 'Titik lokasi kerja belum lengkap. Hubungi HR.',
            ], 422);
        }

        $radius = (int) ($nearest->radius_meter ?? 0);

        if ($radius > 0 && $nearestDistance > $radius) {
            return response()->json([
                'message' => 'Anda di luar area kantor ('.round($nearestDistance).' m dari titik, radius '.$radius.' m).',
            ], 422);
        }

        return $nearest;
    }

    /**
     * The work locations a clock action may be validated against: the employee's
     * explicit assignment when set, otherwise every active work location of their
     * branch. This lets admins configure locations per branch instead of wiring
     * each employee individually.
     *
     * @return Collection<int, WorkLocation>
     */
    private function allowedWorkLocations(Employee $employee): Collection
    {
        if ($employee->work_location_id !== null) {
            $employee->loadMissing('workLocation');

            return $employee->workLocation !== null
                ? collect([$employee->workLocation])
                : collect();
        }

        if ($employee->branch_id === null) {
            return collect();
        }

        return WorkLocation::forTenant($employee->tenant_id)
            ->where('branch_id', $employee->branch_id)
            ->where('status', 'active')
            ->get();
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
