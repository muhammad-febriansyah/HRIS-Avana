<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CaptureReferral;
use App\Models\Partner;
use App\Models\ReferralLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Daftar Perusahaan" inquiry form — what a partner's `?ref=` link
 * actually points to. Submitting it does not create a tenant (self-serve
 * sign-up stays closed, see {@see WelcomeController});
 * it queues a lead the super admin follows up on and, once qualified, turns
 * into a client from the Klien wizard — which is what starts the referral's
 * commission clock.
 */
class ReferralLeadController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/company-inquiry');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $code = $request->cookie(CaptureReferral::COOKIE_NAME);
        $partner = is_string($code) ? Partner::query()->active()->where('code', $code)->first() : null;

        ReferralLead::create([...$validated, 'partner_id' => $partner?->id]);

        return back()->with('success', true);
    }
}
