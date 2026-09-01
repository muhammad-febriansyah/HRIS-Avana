<?php

namespace App\Concerns;

use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserLoginDevice;
use App\Services\LoginSecurity;
use App\Services\SessionRegistry;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

/**
 * The props behind the two-factor and passkey panels.
 *
 * Both the Edit Profil screen and the Keamanan Akun screen render the same
 * panels, so the shape is built once here. The two-factor secret and the
 * recovery codes only travel while the session has recently confirmed its
 * password — see securityPanelProps().
 */
trait BuildsSecurityPanels
{
    /**
     * @return array<string, mixed>
     */
    protected function securityPanelProps(User $user): array
    {
        $props = [
            'canManagePasskeys' => Features::canManagePasskeys(),
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'passkeys' => Features::canManagePasskeys()
                ? $user->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'sessionsAvailable' => SessionRegistry::available(),
            'sessions' => SessionRegistry::forUser($user, session()->getId())->all(),
            'devices' => self::knownDevices($user),
            'loginHistory' => self::loginHistory($user),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $props += $this->twoFactorProps($user);
        }

        return $props;
    }

    /**
     * Every browser or phone this account has signed in from, most recent first.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function knownDevices(User $user): array
    {
        return UserLoginDevice::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->limit(30)
            ->get()
            ->map(fn (UserLoginDevice $device): array => [
                'id' => $device->id,
                'label' => $device->label,
                'channel' => $device->channel,
                'ip_address' => $device->ip_address,
                'login_count' => $device->login_count,
                'first_seen_at' => $device->first_seen_at?->translatedFormat('d M Y, H:i'),
                'last_seen_at' => $device->last_seen_at?->translatedFormat('d M Y, H:i'),
                'last_seen_diff' => $device->last_seen_at?->diffForHumans(),
                'revoked' => $device->revoked_at !== null,
            ])
            ->all();
    }

    /**
     * The account's own recent sign-in activity — including the failures, which
     * are the half that tells the owner someone else is trying.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function loginHistory(User $user): array
    {
        return UserActivityLog::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('properties->email', $user->email);
            })
            ->whereIn('event', ['login', 'logout', 'login_failed', 'login_lockout', 'login_new_device'])
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (UserActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'device' => LoginSecurity::describe((string) ($log->user_agent ?? ''))['label'],
                'created_at' => $log->created_at?->translatedFormat('d M Y, H:i'),
            ])
            ->all();
    }

    /**
     * Build the two-factor half of the panels.
     *
     * The caller has already established that the password was confirmed, so
     * the QR code and the recovery codes can travel as props instead of a
     * second round trip. The secret only ships while an enrolment is still
     * waiting to be confirmed, and the recovery codes only once it has been.
     *
     * @return array{twoFactorEnabled: bool, requiresConfirmation: bool, twoFactorQrCodeSvg: string|null, twoFactorSecretKey: string|null, twoFactorRecoveryCodes: array<int, string>}
     */
    private function twoFactorProps(User $user): array
    {
        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $awaitingConfirmation = ! is_null($user->two_factor_secret) && ! $enabled;

        return [
            'twoFactorEnabled' => $enabled,
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'twoFactorQrCodeSvg' => $awaitingConfirmation ? $user->twoFactorQrCodeSvg() : null,
            'twoFactorSecretKey' => $awaitingConfirmation
                ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret)
                : null,
            'twoFactorRecoveryCodes' => $enabled && $user->two_factor_recovery_codes
                ? $user->recoveryCodes()
                : [],
        ];
    }
}
