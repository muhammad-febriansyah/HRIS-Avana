<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserLoginDevice;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything that happens around a sign-in attempt other than deciding whether
 * the credentials are correct: remembering which devices an account uses,
 * warning the owner when an unfamiliar one gets in, and recording lockouts.
 *
 * Best-effort throughout, like {@see ActivityLogger}: a failure to write the
 * device row must never cost a user their login.
 */
class LoginSecurity
{
    /**
     * Record a successful sign-in against the user's device list and, when the
     * device is one they have never used before, warn them.
     *
     * The very first device an account signs in from is never alerted on —
     * there is nothing to compare it against, and alerting on it would email
     * every user once on the day this shipped.
     */
    public static function recordLogin(
        User $user,
        ?Request $request = null,
        string $channel = 'web',
        ?string $deviceKey = null,
        ?string $deviceLabel = null,
    ): ?UserLoginDevice {
        $request ??= request();

        try {
            $agent = (string) ($request?->userAgent() ?? '');

            // The phone sends a stable device id of its own; prefer it, because
            // an app upgrade changes the user-agent string but not the handset.
            $fingerprint = self::fingerprint($deviceKey ?? $agent, $channel);
            $parsed = self::describe($agent);

            if ($deviceLabel !== null && trim($deviceLabel) !== '') {
                $parsed['label'] = trim($deviceLabel);
            }

            $hadDevices = UserLoginDevice::query()->where('user_id', $user->id)->exists();

            /** @var UserLoginDevice $device */
            $device = UserLoginDevice::query()->firstOrNew([
                'user_id' => $user->id,
                'fingerprint' => $fingerprint,
            ]);

            $isNew = ! $device->exists;

            $device->fill([
                'tenant_id' => $user->tenant_id,
                'label' => $parsed['label'],
                'platform' => $parsed['platform'],
                'browser' => $parsed['browser'],
                'channel' => $channel,
                'ip_address' => $request?->ip(),
                'login_count' => (int) $device->login_count + 1,
                'first_seen_at' => $device->first_seen_at ?? now(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ])->save();

            if ($isNew && $hadDevices) {
                self::alertNewDevice($user, $device);
            }

            return $device;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Record a lockout and tell the account owner, at most once per cooldown
     * window. A lockout the owner did not cause is the earliest visible sign
     * that someone is guessing their password.
     */
    public static function recordLockout(?string $email, ?Request $request = null): void
    {
        $request ??= request();

        ActivityLogger::log(
            'login_lockout',
            'Login dikunci sementara karena terlalu banyak percobaan gagal'
                .($email !== null ? " untuk {$email}" : ''),
            properties: array_filter(['email' => $email]),
            user: null,
            request: $request,
        );

        if ($email === null || ! config('security.lockout.notify_account_owner')) {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $cooldown = max(1, (int) config('security.lockout.notify_cooldown_minutes', 60));

        $shouldNotify = Cache::add('security:lockout-notified:'.$user->id, true, now()->addMinutes($cooldown));

        if (! $shouldNotify) {
            return;
        }

        Notifier::securityAlert(
            $user,
            event: 'login_lockout',
            title: 'Akun Anda dikunci sementara',
            body: 'Terlalu banyak percobaan login gagal, jadi akun Anda dikunci sementara. '
                .'Coba lagi beberapa menit lagi.',
            details: array_filter([
                'Waktu' => now()->translatedFormat('d F Y, H:i'),
                'Alamat IP' => $request?->ip(),
                'Perangkat' => self::describe((string) ($request?->userAgent() ?? ''))['label'],
            ]),
        );
    }

    /**
     * A stable identifier for the client: the user agent plus the channel it
     * arrived on, hashed. Deliberately not IP-based — a phone that changes
     * cell tower is the same device, and alerting on every network change
     * trains people to ignore the alert.
     */
    public static function fingerprint(string $userAgent, string $channel = 'web'): string
    {
        return hash('sha256', $channel.'|'.trim($userAgent));
    }

    /**
     * Turn a user-agent string into something a person can recognise in a list.
     *
     * @return array{label: string, platform: string|null, browser: string|null}
     */
    public static function describe(string $userAgent): array
    {
        if (trim($userAgent) === '') {
            return ['label' => 'Perangkat tidak dikenal', 'platform' => null, 'browser' => null];
        }

        $platform = match (true) {
            Str::contains($userAgent, 'Android') => 'Android',
            Str::contains($userAgent, ['iPhone', 'iPad', 'iOS']) => 'iOS',
            Str::contains($userAgent, ['Windows NT', 'Windows']) => 'Windows',
            Str::contains($userAgent, ['Macintosh', 'Mac OS X']) => 'macOS',
            Str::contains($userAgent, ['CrOS']) => 'ChromeOS',
            Str::contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        // Order matters: Edge and Opera both claim to be Chrome, and Chrome
        // claims to be Safari.
        $browser = match (true) {
            Str::contains($userAgent, ['Edg/', 'Edge/']) => 'Edge',
            Str::contains($userAgent, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($userAgent, 'Firefox/') => 'Firefox',
            Str::contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            Str::contains($userAgent, 'Chrome/') => 'Chrome',
            Str::contains($userAgent, 'Safari/') => 'Safari',
            Str::contains($userAgent, ['Dart/', 'Flutter']) => 'Aplikasi AvanaHR',
            default => null,
        };

        $label = trim(implode(' · ', array_filter([$browser, $platform]))) ?: 'Perangkat tidak dikenal';

        return ['label' => $label, 'platform' => $platform, 'browser' => $browser];
    }

    private static function alertNewDevice(User $user, UserLoginDevice $device): void
    {
        Notifier::securityAlert(
            $user,
            event: 'new_device_login',
            title: 'Login dari perangkat baru',
            body: 'Akun Anda baru saja dipakai masuk dari perangkat yang belum pernah dipakai sebelumnya: '
                .$device->label.'.',
            details: array_filter([
                'Perangkat' => $device->label,
                'Alamat IP' => $device->ip_address,
                'Waktu' => $device->last_seen_at?->translatedFormat('d F Y, H:i'),
            ]),
        );

        ActivityLogger::log(
            'login_new_device',
            'Login dari perangkat baru: '.$device->label,
            properties: ['device_id' => $device->id, 'label' => $device->label],
            user: $user,
        );
    }
}
