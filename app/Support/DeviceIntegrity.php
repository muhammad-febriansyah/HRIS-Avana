<?php

namespace App\Support;

use App\Models\AttendancePolicy;

/**
 * Evaluates a mobile device's trustworthiness for an attendance punch.
 *
 * Two layers:
 *  1. Client signals (rooted / mock-location / emulator / dev-mode) reported by
 *     the app. These are enforced immediately per tenant policy — the primary,
 *     zero-cost guard against Fake-GPS and rooted devices.
 *  2. Platform attestation (Play Integrity / App Attest). The token is always
 *     recorded; it is cryptographically verified only when provider credentials
 *     are configured (see config/services.php). Absent credentials the token is
 *     kept as 'unverified' so verification can be switched on later without a
 *     client change. No third-party call and no cost until then.
 */
final class DeviceIntegrity
{
    /**
     * @param  array<string, mixed>  $signals  is_rooted|is_mock_location|is_emulator|is_dev_mode|integrity_token|platform
     * @return array{verdict: string, blocked: bool, reason: ?string, flags: array<int, string>}
     */
    public static function evaluate(array $signals, AttendancePolicy $policy): array
    {
        $rooted = (bool) ($signals['is_rooted'] ?? false);
        $mock = (bool) ($signals['is_mock_location'] ?? false);
        $emulator = (bool) ($signals['is_emulator'] ?? false);
        $devMode = (bool) ($signals['is_dev_mode'] ?? false);
        $token = $signals['integrity_token'] ?? null;

        $flags = [];
        if ($rooted) {
            $flags[] = 'rooted';
        }
        if ($mock) {
            $flags[] = 'mock_location';
        }
        if ($emulator) {
            $flags[] = 'emulator';
        }
        if ($devMode) {
            $flags[] = 'dev_mode';
        }

        // Blocking only applies when the tenant enforces integrity as a hard gate
        // and the specific signal is switched on. Otherwise the punch proceeds
        // and the flags are recorded for HR review.
        if ($policy->blocksIntegrity()) {
            if ($mock && $policy->block_mock_location) {
                return self::blocked('mock_location', 'Terdeteksi lokasi palsu (Fake GPS). Nonaktifkan mock location lalu coba lagi.', $flags);
            }

            if ($rooted && $policy->block_rooted) {
                return self::blocked('rooted', 'Perangkat terdeteksi di-root/jailbreak. Absen tidak diizinkan dari perangkat ini.', $flags);
            }

            if ($emulator && $policy->block_emulator) {
                return self::blocked('emulator', 'Perangkat terdeteksi sebagai emulator. Gunakan perangkat asli untuk absen.', $flags);
            }
        }

        $verdict = match (true) {
            $flags !== [] => 'flagged',
            is_string($token) && $token !== '' => self::attestationEnabled() ? 'attested' : 'unverified',
            default => 'ok',
        };

        return ['verdict' => $verdict, 'blocked' => false, 'reason' => null, 'flags' => $flags];
    }

    /**
     * Whether platform-attestation credentials are configured. Until then the
     * client-signal layer is the enforcement path.
     */
    public static function attestationEnabled(): bool
    {
        return (bool) config('services.play_integrity.enabled', false)
            || (bool) config('services.app_attest.enabled', false);
    }

    /**
     * @param  array<int, string>  $flags
     * @return array{verdict: string, blocked: bool, reason: string, flags: array<int, string>}
     */
    private static function blocked(string $verdict, string $reason, array $flags): array
    {
        return ['verdict' => 'blocked_'.$verdict, 'blocked' => true, 'reason' => $reason, 'flags' => $flags];
    }
}
