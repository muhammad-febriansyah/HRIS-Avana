<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use JsonException;

final class FaceRecognitionService
{
    /**
     * @param  list<UploadedFile>  $images
     * @return array{embedding: list<float>, model_version: string, dimensions: int, individual_similarities: list<float>}
     */
    public function enroll(array $images): array
    {
        $request = $this->request();
        foreach ($images as $index => $image) {
            $request->attach(
                'images',
                $this->contents($image),
                $image->getClientOriginalName() ?: 'face-'.($index + 1).'.jpg',
                ['Content-Type' => $image->getMimeType() ?: 'image/jpeg'],
            );
        }

        $payload = $this->send(
            fn (): Response => $request->post($this->endpoint('/v1/faces/enroll')),
        );
        $embedding = $this->validatedEmbedding($payload['embedding'] ?? null);
        $modelVersion = (string) ($payload['model_version'] ?? '');
        $dimensions = filter_var($payload['dimensions'] ?? null, FILTER_VALIDATE_INT);

        if ($modelVersion !== $this->expectedModelVersion()
            || $dimensions === false
            || $dimensions !== count($embedding)) {
            throw new FaceRecognitionException(
                'invalid_face_service_response',
                'Face service returned an incompatible enrollment template.',
                true,
            );
        }

        $similarities = array_values(array_map(
            static fn (mixed $value): float => (float) $value,
            is_array($payload['individual_similarities'] ?? null)
                ? $payload['individual_similarities']
                : [],
        ));

        return [
            'embedding' => $embedding,
            'model_version' => $modelVersion,
            'dimensions' => $dimensions,
            'individual_similarities' => $similarities,
        ];
    }

    /**
     * @param  list<float>  $referenceEmbedding
     * @return array{matched: bool, score: float, threshold: float, quality_passed: bool, quality_reasons: list<string>}
     */
    public function verify(
        UploadedFile $image,
        array $referenceEmbedding,
        string $referenceModelVersion,
    ): array {
        try {
            $reference = json_encode($referenceEmbedding, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FaceRecognitionException(
                'invalid_reference_template',
                'Stored face template cannot be encoded.',
                true,
                $exception,
            );
        }

        $request = $this->request()->attach(
            'file',
            $this->contents($image),
            $image->getClientOriginalName() ?: 'selfie.jpg',
            ['Content-Type' => $image->getMimeType() ?: 'image/jpeg'],
        );
        $payload = $this->send(fn (): Response => $request->post(
            $this->endpoint('/v1/faces/verify'),
            [
                'reference_embedding' => $reference,
                'reference_model_version' => $referenceModelVersion,
            ],
        ));

        if (! is_bool($payload['matched'] ?? null)
            || ! is_numeric($payload['score'] ?? null)
            || ! is_numeric($payload['threshold'] ?? null)
            || ! is_bool($payload['quality_passed'] ?? null)) {
            throw new FaceRecognitionException(
                'invalid_face_service_response',
                'Face service returned an invalid verification result.',
                true,
            );
        }

        return [
            'matched' => $payload['matched'],
            'score' => (float) $payload['score'],
            'threshold' => (float) $payload['threshold'],
            'quality_passed' => $payload['quality_passed'],
            'quality_reasons' => array_values(array_map(
                static fn (mixed $value): string => (string) $value,
                is_array($payload['quality_reasons'] ?? null)
                    ? $payload['quality_reasons']
                    : [],
            )),
        ];
    }

    public function detect(UploadedFile $image): int
    {
        $request = $this->request()->attach(
            'file',
            $this->contents($image),
            $image->getClientOriginalName() ?: 'selfie.jpg',
            ['Content-Type' => $image->getMimeType() ?: 'image/jpeg'],
        );
        $payload = $this->send(
            fn (): Response => $request->post($this->endpoint('/v1/faces/detect')),
        );
        $faceCount = filter_var($payload['face_count'] ?? null, FILTER_VALIDATE_INT);

        if ($faceCount === false || $faceCount < 0) {
            throw new FaceRecognitionException(
                'invalid_face_service_response',
                'Face service returned an invalid detection result.',
                true,
            );
        }

        return $faceCount;
    }

    public function expectedModelVersion(): string
    {
        return (string) config('services.face_recognition.model_version', 'sface-2021dec-v1');
    }

    private function request(): PendingRequest
    {
        $apiKey = trim((string) config('services.face_recognition.api_key'));
        if ($apiKey === '' || trim((string) config('services.face_recognition.url')) === '') {
            throw new FaceRecognitionException(
                'face_service_not_configured',
                'Face recognition service is not configured.',
                true,
            );
        }

        return Http::acceptJson()
            ->withHeaders(['X-API-Key' => $apiKey])
            ->connectTimeout((int) config('services.face_recognition.connect_timeout', 3))
            ->timeout((int) config('services.face_recognition.timeout', 20));
    }

    /**
     * @param  callable(): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new FaceRecognitionException(
                'face_service_unavailable',
                'Face recognition service could not be reached.',
                true,
                $exception,
            );
        }

        if (! $response->successful()) {
            $detail = $response->json('detail');

            throw new FaceRecognitionException(
                $response->serverError() ? 'face_service_unavailable' : 'face_rejected',
                is_string($detail) && $detail !== '' ? $detail : 'Face service rejected the image.',
                $response->serverError(),
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new FaceRecognitionException(
                'invalid_face_service_response',
                'Face recognition service returned invalid JSON.',
                true,
            );
        }

        return $payload;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.face_recognition.url'), '/').$path;
    }

    private function contents(UploadedFile $image): string
    {
        $contents = file_get_contents($image->getRealPath());
        if ($contents === false) {
            throw new FaceRecognitionException(
                'invalid_face_image',
                'Uploaded face image could not be read.',
            );
        }

        return $contents;
    }

    /**
     * @return list<float>
     */
    private function validatedEmbedding(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new FaceRecognitionException(
                'invalid_face_service_response',
                'Face service returned an invalid embedding.',
                true,
            );
        }

        $embedding = [];
        foreach ($value as $coordinate) {
            if (! is_numeric($coordinate) || ! is_finite((float) $coordinate)) {
                throw new FaceRecognitionException(
                    'invalid_face_service_response',
                    'Face service returned a non-finite embedding.',
                    true,
                );
            }
            $embedding[] = (float) $coordinate;
        }

        return $embedding;
    }
}
