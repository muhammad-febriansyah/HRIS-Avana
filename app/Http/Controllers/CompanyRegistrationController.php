<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Avana\ReferralController;
use App\Http\Middleware\CaptureReferral;
use App\Models\Partner;
use App\Models\TenantRegistration;
use App\Rules\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Self-serve signup request for organic and referred visitors. Referral
 * attribution is optional and only affects reporting.
 *
 * Submitting the wizard does NOT create a tenant or a login — it queues a
 * {@see TenantRegistration} for the super admin to review, same shape as a
 * mitra application. Approving one is what actually provisions the tenant
 * and its admin account — see
 * {@see ReferralController::approveTenant()}.
 */
class CompanyRegistrationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'industry' => ['required', 'string', 'max:100'],
            'employee_count_range' => ['required', 'string', 'max:50'],
            // Best-effort like the cookie below: a typo or an expired code
            // just falls back to organic rather than blocking the signup.
            'referral_code' => ['nullable', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => [
                'required', 'email', 'max:255',
                'unique:users,email',
                Rule::unique('tenant_registrations', 'admin_email')->where('status', TenantRegistration::STATUS_PENDING),
            ],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
            'terms_accepted' => ['required', 'accepted'],
        ]);

        // Last-click cookie wins when both exist — it reflects the link the
        // applicant actually followed. The typed code is only a fallback for
        // a visitor who landed here without one (e.g. told the code over the
        // phone rather than sent the link).
        $code = $request->cookie(CaptureReferral::COOKIE_NAME) ?: ($validated['referral_code'] ?? null);
        $partner = is_string($code) && $code !== ''
            ? Partner::query()->active()->where('code', Str::upper(trim($code)))->first()
            : null;

        TenantRegistration::create([
            'company_name' => $validated['company_name'],
            'phone' => $validated['phone'],
            'admin_name' => $validated['admin_name'],
            'admin_email' => $validated['admin_email'],
            // Hashed up front so the plain password never sits in the
            // database — TenantProvisioner::createAdmin() carries this
            // straight into the User row on approval without re-hashing it
            // (Laravel's `hashed` cast skips a value that's already a hash),
            // the same way ReferralPartnerService::approve() does for mitra.
            'admin_password' => Hash::make($validated['admin_password']),
            'partner_id' => $partner?->id,
            'source' => $partner === null ? 'organic' : 'referral',
            'industry' => $validated['industry'],
            'employee_count_range' => $validated['employee_count_range'],
            'terms_accepted' => true,
            'status' => TenantRegistration::STATUS_PENDING,
        ]);

        return back()->with('success', 'Pendaftaran diterima. Tim AvanaHR akan meninjau dan mengaktifkan akun Anda.');
    }
}
