<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\BuildsSecurityPanels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Models\UserLoginDevice;
use App\Services\ActivityLogger;
use App\Services\SessionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    use BuildsSecurityPanels;

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        // An enrolment that was started but never confirmed leaves a dangling
        // secret behind; Fortify clears it once the user walks away from it.
        $request->ensureStateIsValid();

        return Inertia::render('settings/security', $this->securityPanelProps($request->user(), $request));
    }

    /**
     * Sign one other session out.
     */
    public function destroySession(Request $request, string $session): RedirectResponse
    {
        $current = $request->session()->getId();

        if ($session === $current) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Sesi ini sedang Anda pakai.']);

            return back();
        }

        $revoked = SessionRegistry::revoke($request->user(), $session);

        if ($revoked) {
            ActivityLogger::log('session_revoked', 'Mengakhiri satu sesi login', user: $request->user());
        }

        Inertia::flash('toast', $revoked
            ? ['type' => 'success', 'message' => 'Sesi diakhiri.']
            : ['type' => 'error', 'message' => 'Sesi tidak ditemukan.']);

        return back();
    }

    /**
     * Sign every other session out, keeping the one making the request.
     *
     * Password-confirmed by the route: this is the button someone reaches for
     * after a device is lost, and it should not be reachable from a session
     * that was simply left open.
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $count = SessionRegistry::revokeOthers($request->user(), $request->session()->getId());

        ActivityLogger::log(
            'session_revoked',
            "Mengakhiri {$count} sesi login lain",
            properties: ['count' => $count],
            user: $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count > 0 ? "{$count} sesi lain diakhiri." : 'Tidak ada sesi lain yang aktif.',
        ]);

        return back();
    }

    /**
     * Revoke a known device: it loses any live session it holds, and signing in
     * from it again counts as a new device, so the owner is warned.
     */
    public function destroyDevice(Request $request, UserLoginDevice $device): RedirectResponse
    {
        abort_unless((int) $device->user_id === (int) $request->user()->id, 403);

        SessionRegistry::revokeByFingerprint($request->user(), $device->fingerprint);

        $device->forceFill(['revoked_at' => now()])->save();

        ActivityLogger::log(
            'device_revoked',
            'Mencabut akses perangkat: '.$device->label,
            properties: ['device_id' => $device->id],
            user: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perangkat dicabut.']);

        return back();
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
