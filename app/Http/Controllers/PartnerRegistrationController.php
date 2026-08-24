<?php

namespace App\Http\Controllers;

use App\Models\PartnerRegistration;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class PartnerRegistrationController extends Controller
{
    /**
     * Show the partner registration form.
     */
    public function create()
    {
        return Inertia::render('public/partner-registration');
    }

    /**
     * Store a new partner registration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'partner_type' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'network_size' => ['nullable', 'string', 'max:255'],
            'network_focus' => ['nullable', 'string', 'max:255'],
            'network_description' => ['nullable', 'string'],
            'social_link' => ['nullable', 'string', 'max:255'],
            'how_did_you_know' => ['nullable', 'string', 'max:255'],
            'terms_accepted' => ['required', 'accepted'],
        ]);

        PartnerRegistration::create($validated);

        return back()->with('success', true);
    }
}
