<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\TrackingLocationBatchRequest;
use App\Models\TrackingSession;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackingController extends Controller
{
    use ResolvesApiEmployee;

    public function __construct(private readonly TrackingService $trackingService) {}

    public function active(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $session = TrackingSession::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('status', TrackingSession::STATUS_ACTIVE)
            ->with('lastLocation')
            ->latest('started_at')
            ->first();

        return response()->json([
            'data' => $this->activeShape($session),
        ]);
    }

    public function store(TrackingLocationBatchRequest $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $session = TrackingSession::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->findOrFail($request->integer('tracking_session_id'));

        $result = $this->trackingService->ingest(
            $employee,
            $session,
            $request->validated('locations'),
        );

        return response()->json([
            'message' => 'Lokasi berhasil disinkronkan.',
            'data' => [
                'stored' => $result['stored'],
                'accepted' => $result['accepted'],
                'rejected' => $result['rejected'],
                'duplicates' => $result['duplicates'],
                'tracking' => $this->trackingService->sessionShape($result['session']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeShape(?TrackingSession $session): ?array
    {
        if ($session === null) {
            return null;
        }

        return [
            ...$this->trackingService->sessionShape($session),
            'last_location' => $session->lastLocation === null ? null : [
                'latitude' => (float) $session->lastLocation->latitude,
                'longitude' => (float) $session->lastLocation->longitude,
                'accuracy' => (float) $session->lastLocation->accuracy,
                'recorded_at' => $session->lastLocation->recorded_at?->toIso8601String(),
            ],
        ];
    }
}
