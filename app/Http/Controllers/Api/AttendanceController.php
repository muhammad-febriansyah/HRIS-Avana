<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceChallenge;
use App\Models\AttendancePolicy;
use App\Models\AttendanceSelfie;
use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\WorkLocation;
use App\Services\LeaveAttendanceMarker;
use App\Support\DeviceIntegrity;
use App\Support\FaceMatcher;
use App\Support\Notifier;
use App\Support\Roster;
use App\Support\TenantTime;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $zone = TenantTime::zoneForBranch($employee->tenant_id, $employee->branch_id);
        $record = $this->todayRecord($employee->tenant_id, $employee->id, $zone);
        $policy = AttendancePolicy::resolve($employee->tenant_id);

        return response()->json([
            'data' => $this->todayShape($record, $zone),
            // Lets the app decide up front whether to force face enrollment or
            // fetch a liveness challenge before showing the clock button.
            'requirements' => [
                // recognition | detection | off — drives the app's face flow.
                'face_mode' => $policy->face_mode ?? AttendancePolicy::FACE_MODE_RECOGNITION,
                'device_binding_enabled' => (bool) ($policy->device_binding_enabled ?? true),
                'require_face_enrollment' => (bool) $policy->require_face_enrollment,
                'require_liveness_challenge' => (bool) $policy->require_liveness_challenge,
                'face_enrolled' => EmployeeFaceEmbedding::where('employee_id', $employee->id)->exists(),
                // Whether "home" is a legal choice today, so the app can offer
                // the mode selector instead of letting the user earn a 422.
                'wfh_approved_today' => LeaveAttendanceMarker::wfhCovers(
                    $employee->tenant_id,
                    $employee->id,
                    Carbon::now($zone)->toDateString(),
                ),
                // So the app labels times with the clock they were taken on.
                'timezone' => $zone,
                'timezone_label' => TenantTime::shortLabel($zone),
            ],
        ]);
    }

    /**
     * Issue a single-use, short-lived liveness challenge. The client must echo
     * the returned nonce with its next clock action, so a captured payload
     * cannot be replayed.
     */
    public function challenge(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        AttendanceChallenge::where('employee_id', $employee->id)
            ->where('expires_at', '<', now())
            ->delete();

        $challenge = AttendanceChallenge::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'nonce' => (string) Str::uuid().Str::random(12),
            'purpose' => 'clock',
            'expires_at' => now()->addMinutes(2),
        ]);

        return response()->json(['data' => [
            'nonce' => $challenge->nonce,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ]]);
    }

    /**
     * The employee's allowed work locations (with geofence radius) so the app
     * can auto-detect whether the user is inside an office area.
     *
     * `scope` travels with them: without it the app would keep reporting "di
     * luar area" for a WFA employee whose clock-in the server accepts anyway.
     */
    public function workLocations(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $scope = $employee->effectiveAttendanceScope(AttendancePolicy::resolve($employee->tenant_id));

        // Under WFA there is no office to be measured against, but the offices
        // are still worth sending so the app can name the nearest one.
        $locations = $scope === AttendancePolicy::SCOPE_ANYWHERE
            ? $this->allowedWorkLocations($employee, AttendancePolicy::SCOPE_ANY_BRANCH)
            : $this->allowedWorkLocations($employee, $scope);

        $data = $locations
            ->map(fn (WorkLocation $w): array => [
                'id' => $w->id,
                'name' => $w->name,
                'latitude' => $w->latitude !== null ? (float) $w->latitude : null,
                'longitude' => $w->longitude !== null ? (float) $w->longitude : null,
                'radius' => (int) ($w->radius_meter ?? 0),
            ])
            ->values();

        return response()->json(['data' => $data, 'scope' => $scope]);
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
            'work_mode' => ['nullable', 'in:office,home'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'face_confidence' => ['nullable', 'numeric'],
            'is_mock_location' => ['nullable', 'boolean'],
            'is_rooted' => ['nullable', 'boolean'],
            'is_emulator' => ['nullable', 'boolean'],
            'is_dev_mode' => ['nullable', 'boolean'],
            'integrity_token' => ['nullable', 'string', 'max:8192'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'nonce' => ['nullable', 'string', 'max:64'],
            // Original clock time for entries queued offline and synced later,
            // bounded to the same day. Past-day fixes go through the attendance
            // correction flow (manager-approved) so they leave an audit trail.
            'clocked_at' => ['nullable', 'date', 'after_or_equal:'.now()->startOfDay()->toDateTimeString()],
            'face_embedding' => ['nullable', 'array', 'min:64', 'max:1024'],
            'face_embedding.*' => ['numeric'],
            'selfie' => ['nullable', 'image', 'max:4096'],
        ]);

        // Resolve the effective clock time; never allow a future timestamp.
        $zone = TenantTime::zoneForBranch($employee->tenant_id, $employee->branch_id);
        $clockedAt = (isset($data['clocked_at']) ? Carbon::parse($data['clocked_at']) : now())
            ->setTimezone($zone);

        if ($clockedAt->isFuture()) {
            $clockedAt = Carbon::now($zone);
        }
        $data['clocked_at'] = $clockedAt;

        $policy = AttendancePolicy::resolve($employee->tenant_id);

        // 1) Device integrity — client signals now; platform attestation once
        //    provider credentials are configured. Blocks per tenant policy.
        $integrity = DeviceIntegrity::evaluate([
            'is_rooted' => $request->boolean('is_rooted'),
            'is_mock_location' => $request->boolean('is_mock_location'),
            'is_emulator' => $request->boolean('is_emulator'),
            'is_dev_mode' => $request->boolean('is_dev_mode'),
            'integrity_token' => $data['integrity_token'] ?? null,
        ], $policy);

        if ($integrity['blocked']) {
            return response()->json(['message' => $integrity['reason']], 422);
        }

        // 2) Anti-replay — consume a single-use liveness challenge when required.
        if ($policy->require_liveness_challenge) {
            $challengeError = $this->consumeChallenge($employee, $data['nonce'] ?? null);

            if ($challengeError instanceof JsonResponse) {
                return $challengeError;
            }
        }

        // 3) Face verification — mode driven by the tenant policy:
        //    recognition = 1:1 identity match; detection = live face only, no
        //    match; off = no face check at all.
        $data['face_confidence'] = null;
        $faceFlags = [];

        if ($policy->usesFaceRecognition()) {
            // Recognition: 1:1 identity match against the enrolled template.
            $enrolled = EmployeeFaceEmbedding::where('employee_id', $employee->id)->first();

            if ($enrolled === null && $policy->require_face_enrollment) {
                return response()->json([
                    'message' => 'Anda wajib mendaftarkan wajah terlebih dahulu sebelum absen. Buka menu Daftar Wajah.',
                ], 422);
            }

            if ($enrolled !== null) {
                $submitted = $data['face_embedding'] ?? null;

                if (! is_array($submitted) || $submitted === []) {
                    if ($policy->blocksFace()) {
                        return response()->json([
                            'message' => 'Verifikasi wajah diperlukan. Aktifkan kamera lalu coba lagi.',
                        ], 422);
                    }

                    $faceFlags[] = 'face_missing';
                } else {
                    $score = FaceMatcher::cosine($enrolled->embedding, $submitted);

                    if ($score < FaceMatcher::THRESHOLD) {
                        if ($policy->blocksFace()) {
                            return response()->json([
                                'message' => 'Wajah tidak cocok dengan data terdaftar. Coba lagi.',
                            ], 422);
                        }

                        $faceFlags[] = 'face_mismatch';
                    }

                    $data['face_confidence'] = round($score, 4);
                }
            }
        } elseif ($policy->usesFace()) {
            // Detection: only prove a live face was captured; no identity match.
            $submitted = $data['face_embedding'] ?? null;

            if (! is_array($submitted) || $submitted === []) {
                if ($policy->blocksFace()) {
                    return response()->json([
                        'message' => 'Verifikasi wajah diperlukan. Aktifkan kamera lalu coba lagi.',
                    ], 422);
                }

                $faceFlags[] = 'face_missing';
            }
        }
        // face_mode 'off' → no face check at all.

        $data['integrity_verdict'] = $integrity['verdict'];
        $data['risk_flags'] = array_values(array_unique([...$integrity['flags'], ...$faceFlags]));

        return $data['type'] === 'in'
            ? $this->clockIn($request, $employee, $data)
            : $this->clockOut($request, $employee, $data);
    }

    /**
     * Validate and consume a liveness challenge nonce. Returns a 422 JsonResponse
     * when the nonce is missing, expired, unknown, or already used; null on success.
     */
    private function consumeChallenge(Employee $employee, ?string $nonce): ?JsonResponse
    {
        if ($nonce === null || $nonce === '') {
            return response()->json([
                'message' => 'Sesi verifikasi tidak ditemukan. Muat ulang lalu coba lagi.',
            ], 422);
        }

        $challenge = AttendanceChallenge::where('employee_id', $employee->id)
            ->where('nonce', $nonce)
            ->first();

        if ($challenge === null || ! $challenge->isUsable()) {
            return response()->json([
                'message' => 'Sesi verifikasi kedaluwarsa atau sudah dipakai. Muat ulang lalu coba lagi.',
            ], 422);
        }

        $challenge->used_at = now();
        $challenge->save();

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockIn(Request $request, Employee $employee, array $data): JsonResponse
    {
        $clockedAt = $data['clocked_at'];

        // A night shift is worked on the day it started, so the punch does not
        // always belong to its own calendar date.
        $workDate = Roster::workDateFor((int) $employee->tenant_id, (int) $employee->id, $clockedAt);

        $attendance = Attendance::firstOrNew([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'date' => $workDate,
        ]);

        if ($attendance->clock_in_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-in hari ini.'], 422);
        }

        // An approved leave owns the day — refuse the clock-in rather than let a
        // check-in overwrite the "Cuti" marker. Pending leave deliberately does
        // not block, or submitting one would be a way to dodge a late mark.
        if (LeaveAttendanceMarker::covers($employee->tenant_id, $employee->id, $workDate)) {
            return response()->json(['message' => 'Anda sedang cuti hari ini, tidak perlu absen.'], 422);
        }

        $workMode = $data['work_mode'] ?? 'office';

        // Working from home is a claim the WFH approval has to back: without it,
        // anyone late or out of town could pick "home" and walk past the radius.
        if ($workMode === 'home' && ! LeaveAttendanceMarker::wfhCovers($employee->tenant_id, $employee->id, $workDate)) {
            return response()->json([
                'message' => 'Tidak ada pengajuan WFH yang disetujui untuk hari ini.',
            ], 422);
        }

        $scope = $workMode === 'home'
            // An approved WFH day answers to no office, exactly like WFA.
            ? AttendancePolicy::SCOPE_ANYWHERE
            : $employee->effectiveAttendanceScope(AttendancePolicy::resolve($employee->tenant_id));

        $geofence = $this->geofenceCheck($employee, $data, $scope);

        if ($geofence instanceof JsonResponse) {
            return $geofence;
        }

        ['status' => $status, 'late_minutes' => $lateMinutes, 'shift_id' => $shiftId] = $this->lateAgainstShift($employee, $clockedAt, $workDate);

        $attendance->fill([
            'branch_id' => $employee->branch_id,
            // Null off-site — there is no office the clock-in belongs to.
            'work_location_id' => $geofence?->id,
            'shift_id' => $shiftId,
            'clock_in_at' => $clockedAt,
            'clock_in_lat' => $data['latitude'] ?? null,
            'clock_in_lng' => $data['longitude'] ?? null,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'work_mode' => $workMode,
            'location_status' => match (true) {
                $workMode === 'home' => 'wfh',
                $geofence === null => 'wfa',
                default => 'inside',
            },
            'face_confidence' => $data['face_confidence'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'integrity_verdict' => $data['integrity_verdict'] ?? null,
            'risk_flags' => ($data['risk_flags'] ?? []) === [] ? null : $data['risk_flags'],
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

        Notifier::attendancePunch(
            $employee->tenant_id,
            $employee->user_id,
            'in',
            $status === 'late' ? 'Clock-in tercatat · Terlambat' : 'Clock-in tercatat',
            'Masuk pukul '.$clockedAt->format('H:i')
                .($lateMinutes > 0 ? " (telat {$lateMinutes} menit)" : ''),
            $clockedAt->toDateString(),
        );

        return response()->json(['message' => 'Clock-in berhasil', 'data' => $this->todayShape($attendance)]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function clockOut(Request $request, Employee $employee, array $data): JsonResponse
    {
        $clockedAt = $data['clocked_at'];

        $attendance = $this->openAttendance($employee, $clockedAt);

        if ($attendance === null || $attendance->clock_in_at === null) {
            return response()->json(['message' => 'Anda belum clock-in hari ini.'], 422);
        }

        if ($attendance->clock_out_at !== null) {
            return response()->json(['message' => 'Anda sudah clock-out hari ini.'], 422);
        }

        // Clock-out follows the mode the day was clocked in under: someone who
        // started an approved WFH day must not be held to the office radius now.
        $scope = $attendance->work_mode === 'home'
            ? AttendancePolicy::SCOPE_ANYWHERE
            : $employee->effectiveAttendanceScope(AttendancePolicy::resolve($employee->tenant_id));

        $geofence = $this->geofenceCheck($employee, $data, $scope);

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
        if (($data['device_id'] ?? null) !== null) {
            $attendance->device_id = $data['device_id'];
        }
        $attendance->integrity_verdict = $data['integrity_verdict'] ?? $attendance->integrity_verdict;
        if (($data['risk_flags'] ?? []) !== []) {
            $attendance->risk_flags = $data['risk_flags'];
        }
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

        Notifier::attendancePunch(
            $employee->tenant_id,
            $employee->user_id,
            'out',
            'Clock-out tercatat',
            'Keluar pukul '.$clockedAt->format('H:i')
                .' · '.round($attendance->work_minutes / 60, 1).' jam kerja',
            $clockedAt->toDateString(),
        );

        return response()->json(['message' => 'Clock-out berhasil', 'data' => $this->todayShape($attendance)]);
    }

    /**
     * Resolve clock-in status against the day's assigned shift: 'late' when the
     * clock time exceeds the shift start plus its tolerance, otherwise 'present'.
     * Returns 'present' with no shift when the day is unscheduled.
     *
     * @return array{status: string, late_minutes: int, shift_id: int|null}
     */
    private function lateAgainstShift(Employee $employee, CarbonInterface $clockedAt, ?string $workDate = null): array
    {
        return Roster::evaluateFor($employee, $clockedAt, $workDate);
    }

    /**
     * The shift this clock-out closes.
     *
     * The roster day is tried first, which is the punch's own date on a normal
     * shift and the previous one on a night shift. Someone who overruns past
     * their shift end would fall outside that window and be told they never
     * clocked in, so a still-open clock-in from the day before is accepted as
     * long as it is under a day old.
     */
    private function openAttendance(Employee $employee, CarbonInterface $clockedAt): ?Attendance
    {
        $workDate = Roster::workDateFor((int) $employee->tenant_id, (int) $employee->id, $clockedAt);

        $onWorkDate = Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $workDate)
            ->first();

        if ($onWorkDate !== null) {
            return $onWorkDate;
        }

        return Attendance::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereNotNull('clock_in_at')
            ->whereNull('clock_out_at')
            ->where('clock_in_at', '>=', $clockedAt->copy()->subDay())
            ->orderByDesc('clock_in_at')
            ->first();
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
    private function geofenceCheck(Employee $employee, array $data, string $scope): WorkLocation|JsonResponse|null
    {
        // GPS is required even for WFA: the point is still recorded, and the
        // mock-location check upstream is worthless without it.
        if (! isset($data['latitude'], $data['longitude'])) {
            return response()->json([
                'message' => 'Lokasi GPS tidak terdeteksi. Aktifkan izin lokasi lalu coba lagi.',
            ], 422);
        }

        // WFA answers to no office, so there is no radius to be outside of.
        if ($scope === AttendancePolicy::SCOPE_ANYWHERE) {
            return null;
        }

        $locations = $this->allowedWorkLocations($employee, $scope);

        if ($locations->isEmpty()) {
            return response()->json([
                'message' => 'Lokasi kerja belum diatur admin. Hubungi HR.',
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
    private function allowedWorkLocations(Employee $employee, string $scope): Collection
    {
        // Any office in the tenant will do, whatever the employee is assigned to.
        if ($scope === AttendancePolicy::SCOPE_ANY_BRANCH) {
            return WorkLocation::forTenant($employee->tenant_id)
                ->where('status', 'active')
                ->get();
        }

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

    private function todayRecord(int $tenantId, int $employeeId, ?string $zone = null): ?Attendance
    {
        $zone ??= (string) config('app.timezone');

        // Mid-shift at 02:00, a night worker's day is still yesterday's roster
        // row — reading today's would show them as not yet clocked in.
        return Attendance::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('date', Roster::workDateFor($tenantId, $employeeId, Carbon::now($zone)))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function todayShape(?Attendance $a, ?string $zone = null): array
    {
        $zone ??= (string) config('app.timezone');
        $nextAction = 'done';
        if ($a === null || $a->clock_in_at === null) {
            $nextAction = 'in';
        } elseif ($a->clock_out_at === null) {
            $nextAction = 'out';
        }

        return [
            'date' => Carbon::now($zone)->toDateString(),
            // Stored as the office's own wall clock, so it reads back as the
            // hour that office saw with no conversion.
            'clock_in' => $a?->clock_in_at?->format('H:i'),
            'clock_out' => $a?->clock_out_at?->format('H:i'),
            // Full ISO timestamp so the app can tick worked-hours to the second.
            'clock_in_at' => $a?->clock_in_at?->toIso8601String(),
            'next_action' => $nextAction,
            'work_mode' => $a?->work_mode,
            'summary' => [
                'status' => $a?->status,
                'work_minutes' => (int) ($a?->work_minutes ?? 0),
            ],
        ];
    }
}
