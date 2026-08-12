<?php

namespace App\Concerns;

use App\Models\User;
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
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $props += $this->twoFactorProps($user);
        }

        return $props;
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
