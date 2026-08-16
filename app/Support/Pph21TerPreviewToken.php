<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class Pph21TerPreviewToken
{
    /**
     * @param  array{checksum: string, effective_start_date: string, source: string, reason: string, user_id: int}  $context
     */
    public static function issue(array $context): string
    {
        return Crypt::encryptString(json_encode([
            ...$context,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{checksum: string, effective_start_date: string, source: string, reason: string, user_id: int}  $expected
     */
    public static function assertMatches(string $token, array $expected): void
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['preview_token' => 'Preview tidak valid. Jalankan preview ulang.']);
        }

        if (! is_array($payload) || (int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            throw ValidationException::withMessages(['preview_token' => 'Preview sudah kedaluwarsa. Jalankan preview ulang.']);
        }

        foreach ($expected as $key => $value) {
            if (! hash_equals((string) $value, (string) ($payload[$key] ?? ''))) {
                throw ValidationException::withMessages(['preview_token' => 'Berkas atau parameter berubah setelah preview. Jalankan preview ulang.']);
            }
        }
    }
}
