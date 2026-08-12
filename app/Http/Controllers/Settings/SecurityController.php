<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\BuildsSecurityPanels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('settings/security', $this->securityPanelProps($request->user()));
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
