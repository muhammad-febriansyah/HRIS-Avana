<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\EmployeeFaceEmbedding;
use App\Models\FaceScanLog;
use App\Models\Tenant;
use App\Services\FaceRecognitionException;
use App\Services\FaceRecognitionService;
use App\Services\FaceScanLogger;
use App\Support\FaceMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Permanently remove the caller's encrypted biometric template.
     */
    public function destroy(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        EmployeeFaceEmbedding::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->delete();

        $this->scanLogger->record($employee, [
            'context' => FaceScanLog::CONTEXT_ENROLL,
            'outcome' => 'ok',
            'reason' => 'deleted',
            'message' => 'Data wajah berhasil dihapus',
            'device' => $this->deviceFrom($request),
        ], $request);

        return response()->json(['message' => 'Data wajah berhasil dihapus']);
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
                'message' => $this->enrollmentFailureMessage($exception),
            ], $exception->unavailable ? 503 : 422);
        }

        $duplicate = DB::transaction(function () use ($employee, $result): ?array {
            Tenant::query()->whereKey($employee->tenant_id)->lockForUpdate()->firstOrFail();

            $duplicate = $this->duplicateEnrollment(
                $result['embedding'],
                $result['model_version'],
                $employee->tenant_id,
                $employee->id,
            );
            if ($duplicate !== null) {
                return $duplicate;
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

            return null;
        }, 3);

        if ($duplicate !== null) {
            $this->scanLogger->record($employee, [
                'context' => FaceScanLog::CONTEXT_ENROLL,
                'outcome' => 'blocked',
                'reason' => 'duplicate_face',
                'message' => 'Wajah sudah terdaftar pada akun lain',
                'metrics' => [
                    'duplicate_score' => $duplicate['score'],
                    'duplicate_threshold' => $this->duplicateThreshold(),
                    'conflicting_employee_id' => $duplicate['employee_id'],
                ],
                'device' => $this->deviceFrom($request),
            ], $request);

            return response()->json([
                'message' => 'Wajah ini sudah terdaftar pada akun lain. Gunakan wajah pemilik akun.',
            ], 409);
        }

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

    private function enrollmentFailureMessage(FaceRecognitionException $exception): string
    {
        if ($exception->unavailable) {
            return 'Layanan pengenalan wajah sedang tidak tersedia. Coba lagi.';
        }

        $detail = $exception->getMessage();
        if (preg_match('/image (\d+).*found 0/i', $detail, $matches) === 1) {
            return "Wajah tidak terdeteksi pada foto ke-{$matches[1]}. Pastikan wajah tegak, terang, dan terlihat penuh.";
        }
        if (preg_match('/image (\d+).*found ([2-9]|[1-9]\d+)/i', $detail, $matches) === 1) {
            return "Terdeteksi lebih dari satu wajah pada foto ke-{$matches[1]}. Pastikan hanya wajah Anda yang terlihat.";
        }
        if (str_contains($detail, 'quality check failed')) {
            return 'Kualitas foto wajah belum cukup. Pastikan wajah terang, tidak buram, dan berada di tengah.';
        }
        if (str_contains($detail, 'not the same identity')) {
            return 'Wajah pada setiap foto tidak konsisten. Ulangi pendaftaran dengan satu orang yang sama.';
        }

        return 'Wajah belum dapat didaftarkan. Pastikan hanya satu wajah, terang, dan tidak buram.';
    }

    /**
     * @param  list<float>  $candidate
     * @return array{employee_id: int, score: float}|null
     */
    private function duplicateEnrollment(
        array $candidate,
        string $modelVersion,
        int|string $tenantId,
        int $employeeId,
    ): ?array {
        $threshold = $this->duplicateThreshold();
        $bestMatch = null;

        $faces = EmployeeFaceEmbedding::query()
            ->forTenant($tenantId)
            ->where('employee_id', '!=', $employeeId)
            ->where('model_version', $modelVersion)
            ->where('dimensions', count($candidate))
            ->whereNotNull('embedding_ciphertext')
            ->lazyById(100);

        foreach ($faces as $face) {
            $reference = $face->recognitionEmbedding();
            if ($reference === null) {
                continue;
            }

            $score = FaceMatcher::cosine($candidate, $reference);
            if ($score >= $threshold && ($bestMatch === null || $score > $bestMatch['score'])) {
                $bestMatch = [
                    'employee_id' => (int) $face->employee_id,
                    'score' => round($score, 4),
                ];
            }
        }

        return $bestMatch;
    }

    private function duplicateThreshold(): float
    {
        return max(-1.0, min(1.0, (float) config('services.face_recognition.duplicate_threshold', 0.6)));
    }
}
