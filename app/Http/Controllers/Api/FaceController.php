<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeFaceEmbedding;
use App\Models\FaceScanLog;
use App\Services\FaceScanLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Employee self-service face enrollment for attendance verification. */
class FaceController extends Controller
{
    use ResolvesApiEmployee;

    public function __construct(private readonly FaceScanLogger $scanLogger) {}

    /**
     * Whether the caller has an enrolled face on record.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $face = EmployeeFaceEmbedding::where('employee_id', $employee->id)->first();

        return response()->json(['data' => [
            'enrolled' => $face !== null,
            'dimensions' => $face?->dimensions,
            'enrolled_at' => $face?->enrolled_at?->toDateTimeString(),
        ]]);
    }

    /**
     * Enroll (or re-enroll) the caller's face embedding.
     */
    public function enroll(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'embedding' => ['required', 'array', 'min:64', 'max:1024'],
            'embedding.*' => ['numeric'],
        ]);

        $embedding = array_map(static fn ($value): float => (float) $value, $data['embedding']);

        EmployeeFaceEmbedding::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'tenant_id' => $employee->tenant_id,
                'embedding' => $embedding,
                'dimensions' => count($embedding),
                'enrolled_at' => now(),
            ],
        );

        $this->scanLogger->record($employee, [
            'context' => FaceScanLog::CONTEXT_ENROLL,
            'outcome' => 'ok',
            'reason' => 'enrolled',
            'message' => 'Wajah berhasil didaftarkan',
            'metrics' => ['embedding_dimensions' => count($embedding)],
            'device' => $this->deviceFrom($request),
        ], $request);

        return response()->json(['message' => 'Wajah berhasil didaftarkan']);
    }

    /**
     * Record device-side scan diagnostics.
     *
     * Face detection runs entirely on the phone, so a scan that never succeeds
     * leaves no server-side trace at all — the employee just watches the same
     * hint repeat. The app posts what it measured here (in batches, so a scan
     * loop doesn't become a request loop) and support can then see whether the
     * face was missed, off-angle, or rejected by the expression check, and on
     * which device.
     */
    public function log(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*.context' => ['required', Rule::in(FaceScanLog::CONTEXTS)],
            'events.*.outcome' => ['required', Rule::in(FaceScanLog::OUTCOMES)],
            'events.*.reason' => ['required', 'string', 'max:40'],
            'events.*.step' => ['nullable', 'integer', 'between:0,9'],
            'events.*.message' => ['nullable', 'string', 'max:255'],
            'events.*.metrics' => ['nullable', 'array'],
            'device' => ['nullable', 'array'],
            'device.platform' => ['nullable', 'string', 'max:20'],
            'device.os_version' => ['nullable', 'string', 'max:60'],
            'device.model' => ['nullable', 'string', 'max:120'],
            'device.app_version' => ['nullable', 'string', 'max:30'],
            'device.device_id' => ['nullable', 'string', 'max:191'],
        ]);

        foreach ($data['events'] as $event) {
            $this->scanLogger->record($employee, [
                ...$event,
                'device' => $data['device'] ?? [],
            ], $request);
        }

        return response()->json(['message' => 'Tercatat'], 202);
    }

    /**
     * Device metadata sent alongside ordinary API calls, if the app included it.
     *
     * @return array<string, mixed>
     */
    private function deviceFrom(Request $request): array
    {
        $device = $request->input('device');

        return is_array($device) ? $device : [];
    }
}
