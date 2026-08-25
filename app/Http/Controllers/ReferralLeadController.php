<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CaptureReferral;
use App\Models\Partner;
use App\Models\ReferralLead;
use App\Rules\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public "Daftar Perusahaan" page — what a partner's `?ref=` link points to.
 *
 * A visitor carrying a valid referral cookie gets the self-serve signup
 * wizard ({@see CompanyRegistrationController}, which provisions a real
 * tenant immediately). Everyone else (no cookie, or the partner behind it
 * went inactive) still gets the plain inquiry form below — general organic
 * self-serve sign-up stays closed on purpose, see {@see WelcomeController}.
 */
class ReferralLeadController extends Controller
{
    public function create(Request $request): Response
    {
        $code = $request->cookie(CaptureReferral::COOKIE_NAME);
        $partner = is_string($code) ? Partner::query()->active()->where('code', $code)->first() : null;

        if ($partner !== null) {
            return Inertia::render('public/company-registration', ['partnerCode' => $partner->code]);
        }

        return Inertia::render('public/company-inquiry');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $code = $request->cookie(CaptureReferral::COOKIE_NAME);
        $partner = is_string($code) ? Partner::query()->active()->where('code', $code)->first() : null;

        ReferralLead::create([...$validated, 'partner_id' => $partner?->id]);

        return back()->with('success', 'Data Anda sudah kami terima. Tim AvanaHR akan segera menghubungi Anda.');
    }
}
