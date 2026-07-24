<?php

namespace App\Services;

use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging sender (HTTP v1 API). Dependency-free: mints an
 * OAuth2 access token from the service-account JSON via a signed JWT, then
 * pushes to a user's registered device tokens. A no-op when no service account
 * is configured, so calling it is always safe.
 */
final class FcmService
{
    /**
     * Whether FCM is configured (service account present).
     */
    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Push a notification to every active device token of the given users.
     *
     * @param  array<int, int>  $userIds
     * @param  array<string, string|int>  $data  deep-link payload (type/id, ...)
     */
    public function pushToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        if ($userIds === [] || ! $this->enabled()) {
            return;
        }

        $tokens = UserDevice::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->unique()
            ->all();

        if ($tokens === []) {
            return;
        }

        $accessToken = $this->accessToken();
        $creds = $this->credentials();

        if ($accessToken === null || $creds === null) {
            return;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$creds['project_id']}/messages:send";
        $stringData = array_map(static fn ($value): string => (string) $value, $data);

        foreach ($tokens as $token) {
            try {
                Http::withToken($accessToken)->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $stringData,
                        // High-importance channel + sound so the mobile app shows
                        // a heads-up notification with a tone (Messenger-style).
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'avana_high',
                                'sound' => 'default',
                                'default_vibrate_timings' => true,
                            ],
                        ],
                        'apns' => [
                            'payload' => [
                                'aps' => ['sound' => 'default'],
                            ],
                        ],
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::warning('FCM send failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * A cached OAuth2 access token for the FCM HTTP v1 API, minted from the
     * service account via a signed JWT (RS256).
     */
    private function accessToken(): ?string
    {
        $creds = $this->credentials();

        if ($creds === null) {
            return null;
        }

        return Cache::remember('fcm_access_token', now()->addMinutes(50), function () use ($creds): ?string {
            $now = time();
            $jwt = $this->signJwt([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $creds['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ], $creds['private_key']);

            if ($jwt === null) {
                return null;
            }

            $response = Http::asForm()->post($creds['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }

    /**
     * Sign a service-account JWT with RS256, or null if signing fails.
     *
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $claims, string $privateKey): ?string
    {
        $input = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$this->base64Url((string) json_encode($claims));

        $signature = '';

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $input.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * The decoded service-account credentials, or null when not configured.
     *
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null
     */
    private function credentials(): ?array
    {
        $path = config('services.firebase.credentials');

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || ! isset($json['private_key'], $json['client_email'])) {
            return null;
        }

        return [
            'project_id' => (string) ($json['project_id'] ?? config('services.firebase.project_id', '')),
            'client_email' => (string) $json['client_email'],
            'private_key' => (string) $json['private_key'],
            'token_uri' => (string) ($json['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
        ];
    }
}
