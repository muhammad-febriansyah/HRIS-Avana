<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        // An enrolment that was started but never confirmed leaves a dangling
        // secret behind; Fortify clears it once the user walks away from it.
        $request->ensureStateIsValid();

        $props = [
            'canManagePasskeys' => Features::canManagePasskeys(),
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'passkeys' => Features::canManagePasskeys()
                ? $request->user()
                    ->passkeys()
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
            $props += $this->twoFactorProps($request->user());
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Build the two-factor half of the security page.
     *
     * The page already sits behind a password confirmation, so the QR code and
     * the recovery codes can travel as props instead of a second round trip.
     * The secret only ships while an enrolment is still waiting to be
     * confirmed, and the recovery codes only once it has been.
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

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
