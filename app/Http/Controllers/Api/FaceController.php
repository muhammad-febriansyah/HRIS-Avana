<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeFaceEmbedding;
use App\Models\FaceScanLog;
use App\Services\FaceRecognitionException;
use App\Services\FaceRecognitionService;
use App\Services\FaceScanLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Employee self-service face enrollment for attendance verification. */
class FaceController extends Controller
{
    use ResolvesApiEmployee;

    public function __construct(
        private readonly FaceScanLogger $scanLogger,
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * Whether the caller has an enrolled face on record.
     */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $face = EmployeeFaceEmbedding::where('employee_id', $employee->id)->first();
        $enrolled = $face?->isCompatibleWith($this->faceRecognition->expectedModelVersion()) ?? false;

        return response()->json(['data' => [
            'enrolled' => $enrolled,
            'requires_reenrollment' => $face !== null && ! $enrolled,
            'dimensions' => $enrolled ? $face->dimensions : null,
            'model_version' => $enrolled ? $face->model_version : null,
            'enrolled_at' => $enrolled ? $face->enrolledAtString() : null,
        ]]);
    }

    /**
     * Enroll (or re-enroll) from three to five liveness-gated camera frames.
     */
    public function enroll(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'images' => ['required', 'array', 'min:3', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ], [
            'images.required' => 'Foto wajah wajib dikirim.',
            'images.array' => 'Format foto wajah tidak valid.',
            'images.min' => 'Kirim minimal :min foto wajah.',
            'images.max' => 'Kirim maksimal :max foto wajah.',
            'images.*.required' => 'Setiap foto wajah wajib dikirim.',
            'images.*.image' => 'Setiap file harus berupa gambar.',
            'images.*.mimes' => 'Format foto wajah harus JPG, JPEG, atau PNG.',
            'images.*.max' => 'Ukuran setiap foto wajah maksimal 4 MB.',
        ]);

        try {
            $result = $this->faceRecognition->enroll($data['images']);
        } catch (FaceRecognitionException $exception) {
            $this->scanLogger->record($employee, [
                'context' => FaceScanLog::CONTEXT_ENROLL,
                'outcome' => 'blocked',
                'reason' => $exception->reason,
                'message' => $exception->getMessage(),
                'device' => $this->deviceFrom($request),
            ], $request);

            return response()->json([
                'message' => $exception->unavailable
                    ? 'Layanan pengenalan wajah sedang tidak tersedia. Coba lagi.'
                    : 'Wajah belum dapat didaftarkan. Pastikan hanya satu wajah, terang, dan tidak buram.',
            ], $exception->unavailable ? 503 : 422);
        }

        EmployeeFaceEmbedding::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'tenant_id' => $employee->tenant_id,
                // The legacy on-device vector remains only for schema
                // compatibility. New biometric templates use Laravel's
                // encrypted cast in embedding_ciphertext.
                'embedding' => [],
                'embedding_ciphertext' => $result['embedding'],
                'dimensions' => $result['dimensions'],
                'model_version' => $result['model_version'],
                'enrolled_at' => now(),
            ],
        );

        $this->scanLogger->record($employee, [
            'context' => FaceScanLog::CONTEXT_ENROLL,
            'outcome' => 'ok',
            'reason' => 'enrolled',
            'message' => 'Wajah berhasil didaftarkan',
            'metrics' => [
                'embedding_dimensions' => $result['dimensions'],
                'model_version' => $result['model_version'],
                'individual_similarities' => $result['individual_similarities'],
            ],
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
