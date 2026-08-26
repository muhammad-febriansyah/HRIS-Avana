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
 * Public "Daftar Perusahaan" page for both organic and referred visitors.
 *
 * Referral attribution is optional. A valid cookie is displayed as context
 * and stored on the pending registration; it does not change the form.
 */
class ReferralLeadController extends Controller
{
    public function create(Request $request): Response
    {
        $partner = null;
        $queryCode = $request->query('ref');

        if (is_string($queryCode) && $queryCode !== '') {
            $partner = Partner::query()->with('user:id,name')->active()->where('code', $queryCode)->first();
        }

        if ($partner === null) {
            $cookieCode = $request->cookie(CaptureReferral::COOKIE_NAME);
            $partner = is_string($cookieCode)
                ? Partner::query()->with('user:id,name')->active()->where('code', $cookieCode)->first()
                : null;
        }

        return Inertia::render('public/company-registration', [
            'partnerCode' => $partner?->code,
            'partnerName' => $partner?->user?->name,
        ]);
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
