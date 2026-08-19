<?php

namespace App\Services;

use App\Events\EmployeeLocationUpdated;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLastLocation;
use App\Models\TrackingLocation;
use App\Models\TrackingSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrackingService
{
    private const MAX_ACCURACY_METERS = 100;

    private const DUPLICATE_DISTANCE_METERS = 3;

    private const DUPLICATE_WINDOW_SECONDS = 10;

    private const MAX_PLAUSIBLE_SPEED_METERS_PER_SECOND = 55;

    public function __construct(private readonly DistanceService $distanceService) {}

    /**
     * Opens the GPS session that rides along with a clock-in. Returns null
     * without creating anything when the tenant has switched Live Tracking
     * off — attendance still clocks in fine, it just travels alone.
     */
    public function startForAttendance(Attendance $attendance, Employee $employee): ?TrackingSession
    {
        if (! $employee->tenant->hasFeature('tracking')) {
            return null;
        }

        return DB::transaction(function () use ($attendance, $employee): TrackingSession {
            $lockedAttendance = Attendance::query()->lockForUpdate()->findOrFail($attendance->id);

            return TrackingSession::query()->firstOrCreate(
                ['attendance_id' => $lockedAttendance->id],
                [
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'started_at' => $lockedAttendance->clock_in_at,
                    'status' => TrackingSession::STATUS_ACTIVE,
                ],
            );
        }, 3);
    }

    public function completeForAttendance(Attendance $attendance, CarbonInterface $endedAt): ?TrackingSession
    {
        return DB::transaction(function () use ($attendance, $endedAt): ?TrackingSession {
            $session = TrackingSession::query()
                ->where('attendance_id', $attendance->id)
                ->lockForUpdate()
                ->first();

            if ($session === null || $session->status !== TrackingSession::STATUS_ACTIVE) {
                return $session;
            }

            $session->forceFill([
                'ended_at' => $endedAt,
                'total_duration_seconds' => max(0, (int) $session->started_at->diffInSeconds($endedAt)),
                'status' => TrackingSession::STATUS_COMPLETED,
            ])->save();

            return $session->refresh();
        }, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $points
     * @return array{stored: int, accepted: int, rejected: int, duplicates: int, session: TrackingSession}
     */
    public function ingest(Employee $employee, TrackingSession $trackingSession, array $points): array
    {
        $latestForBroadcast = null;

        $result = DB::transaction(function () use ($employee, $trackingSession, $points, &$latestForBroadcast): array {
            $session = TrackingSession::query()->lockForUpdate()->findOrFail($trackingSession->id);

            if ((int) $session->tenant_id !== (int) $employee->tenant_id
                || (int) $session->employee_id !== (int) $employee->id) {
                abort(404);
            }

            if ($session->status !== TrackingSession::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'tracking_session_id' => 'Sesi tracking sudah tidak aktif.',
                ]);
            }

            usort($points, fn (array $left, array $right): int => strcmp(
                (string) $left['recorded_at'],
                (string) $right['recorded_at'],
            ));

            $previous = TrackingLocation::query()
                ->where('tracking_session_id', $session->id)
                ->where('is_accepted', true)
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            $stored = 0;
            $accepted = 0;
            $rejected = 0;
            $duplicates = 0;
            $distanceAdded = 0;
            $firstAcceptedCoordinates = null;

            foreach ($points as $point) {
                if (TrackingLocation::query()
                    ->where('tracking_session_id', $session->id)
                    ->where('client_uuid', $point['client_uuid'])
                    ->exists()) {
                    $duplicates++;

                    continue;
                }

                $recordedAt = Carbon::parse((string) $point['recorded_at'])->setTimezone((string) config('app.timezone'));
                $latitude = (float) $point['latitude'];
                $longitude = (float) $point['longitude'];
                $accuracy = (float) $point['accuracy'];
                $isAccepted = true;
                $isSuspicious = (bool) ($point['is_mocked'] ?? false);
                $reason = $isSuspicious ? 'mock_location' : null;
                $distance = 0;

                if ($accuracy > self::MAX_ACCURACY_METERS) {
                    $isAccepted = false;
                    $reason = 'poor_accuracy';
                } elseif ($previous !== null) {
                    $seconds = $previous->recorded_at->diffInSeconds($recordedAt, false);

                    if ($seconds <= 0) {
                        $isAccepted = false;
                        $reason = 'out_of_order';
                    } else {
                        $calculatedDistance = $this->distanceService->meters(
                            (float) $previous->latitude,
                            (float) $previous->longitude,
                            $latitude,
                            $longitude,
                        );

                        if ($calculatedDistance < self::DUPLICATE_DISTANCE_METERS
                            && $seconds <= self::DUPLICATE_WINDOW_SECONDS) {
                            $isAccepted = false;
                            $reason = 'near_duplicate';
                        } elseif (($calculatedDistance / $seconds) > self::MAX_PLAUSIBLE_SPEED_METERS_PER_SECOND) {
                            $isAccepted = false;
                            $isSuspicious = true;
                            $reason = 'impossible_movement';
                        } else {
                            $distance = (int) round($calculatedDistance);
                        }
                    }
                }

                $location = TrackingLocation::query()->create([
                    'tracking_session_id' => $session->id,
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'client_uuid' => $point['client_uuid'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'accuracy' => $accuracy,
                    'altitude' => $point['altitude'] ?? null,
                    'speed' => $point['speed'] ?? null,
                    'heading' => $point['heading'] ?? null,
                    'is_mocked' => (bool) ($point['is_mocked'] ?? false),
                    'battery_level' => $point['battery_level'] ?? null,
                    'is_suspicious' => $isSuspicious,
                    'is_accepted' => $isAccepted,
                    'suspicion_reason' => $reason,
                    'distance_meters' => $isAccepted ? $distance : 0,
                    'recorded_at' => $recordedAt,
                ]);
                $stored++;

                if (! $isAccepted) {
                    $rejected++;

                    continue;
                }

                $accepted++;
                $distanceAdded += $distance;
                $firstAcceptedCoordinates ??= [$latitude, $longitude];
                $previous = $location;

                $lastLocation = EmployeeLastLocation::query()
                    ->where('tenant_id', $employee->tenant_id)
                    ->where('employee_id', $employee->id)
                    ->lockForUpdate()
                    ->first();

                if ($lastLocation === null || $lastLocation->recorded_at->lt($recordedAt)) {
                    $lastLocation ??= new EmployeeLastLocation;
                    $lastLocation->fill([
                        'tenant_id' => $employee->tenant_id,
                        'employee_id' => $employee->id,
                        'tracking_session_id' => $session->id,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'accuracy' => $accuracy,
                        'speed' => $point['speed'] ?? null,
                        'heading' => $point['heading'] ?? null,
                        'battery_level' => $point['battery_level'] ?? null,
                        'is_mocked' => (bool) ($point['is_mocked'] ?? false),
                        'is_suspicious' => $isSuspicious,
                        'recorded_at' => $recordedAt,
                    ])->save();
                    $latestForBroadcast = $lastLocation->fresh();
                }
            }

            if ($accepted > 0 && $previous !== null) {
                $session->forceFill([
                    'start_latitude' => $session->start_latitude ?? $firstAcceptedCoordinates[0],
                    'start_longitude' => $session->start_longitude ?? $firstAcceptedCoordinates[1],
                    'end_latitude' => $previous->latitude,
                    'end_longitude' => $previous->longitude,
                    'last_location_at' => $previous->recorded_at,
                    'total_distance_meters' => (int) $session->total_distance_meters + $distanceAdded,
                ])->save();
            }

            return [
                'stored' => $stored,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'duplicates' => $duplicates,
                'session' => $session->refresh(),
            ];
        }, 3);

        if ($latestForBroadcast instanceof EmployeeLastLocation) {
            EmployeeLocationUpdated::dispatch($latestForBroadcast);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function sessionShape(?TrackingSession $session): ?array
    {
        if ($session === null) {
            return null;
        }

        return [
            'id' => $session->id,
            'attendance_id' => $session->attendance_id,
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'last_location_at' => $session->last_location_at?->toIso8601String(),
            'total_distance_meters' => (int) $session->total_distance_meters,
        ];
    }
}
