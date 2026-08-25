<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Avana\TenantController;
use App\Http\Controllers\Avana\TenantSubscriptionController;
use App\Http\Middleware\CaptureReferral;
use App\Models\Partner;
use App\Models\ReferralLead;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Services\TenantProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Self-serve tenant signup for a visitor arriving with a valid referral
 * cookie — see {@see ReferralLeadController::create()}, which is what routes
 * a referred visitor to the wizard this controller's `store()` submits to
 * instead of the plain inquiry form. Unattributed/organic visitors never
 * reach this endpoint; general self-serve sign-up stays closed on purpose.
 *
 * Provisions a real Tenant + admin login immediately (trial, no package
 * yet — see {@see TenantSubscriptionController}
 * for where the tenant picks a package and pays, once logged in), the same
 * way {@see TenantController::store()} does for
 * a super admin, but auto-logs the new admin in instead of showing
 * credentials for someone else to hand over.
 */
class CompanyRegistrationController extends Controller
{
    /**
     * How long a self-serve trial lasts before it needs a package —
     * mirrors {@see TenantController}'s default.
     */
    private const TRIAL_DAYS = 14;

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $code = $request->cookie(CaptureReferral::COOKIE_NAME);
        $partner = is_string($code) ? Partner::query()->active()->where('code', $code)->first() : null;

        if ($partner === null) {
            return back()->with('error', 'Link referral Anda sudah kedaluwarsa. Silakan buka ulang link dari mitra Anda.');
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new PhoneNumber],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
            'terms_accepted' => ['required', 'accepted'],
        ]);

        $admin = DB::transaction(function () use ($validated, $partner, $provisioner): User {
            $slug = $this->uniqueSlug($validated['company_name']);
            $start = now();

            $tenant = Tenant::create([
                'name' => $validated['company_name'],
                'company_name' => $validated['company_name'],
                'slug' => $slug,
                'package_id' => null,
                'partner_id' => $partner->id,
                'status' => 'trial',
                'max_users' => 0,
                'max_employees' => 0,
                'max_branches' => 0,
                'billing_status' => 'active',
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addDays(self::TRIAL_DAYS)->toDateString(),
            ]);

            // Keeps this signup showing up in the super admin's existing
            // Leads/Konversi reporting the same way a manually-converted
            // lead does — see ReferralController::index().
            ReferralLead::create([
                'company_name' => $validated['company_name'],
                'contact_name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'phone' => $validated['phone'],
                'partner_id' => $partner->id,
                'status' => ReferralLead::STATUS_CONVERTED,
                'tenant_id' => $tenant->id,
                'converted_at' => now(),
            ]);

            $provisioner->provision($tenant);

            // The visitor's own chosen password is carried straight through
            // (not auto-generated), the same way ReferralPartnerService does
            // for an approved mitra application — nobody has to relay it.
            return $provisioner->createAdmin(
                $tenant,
                $validated['admin_name'],
                $validated['admin_email'],
                $validated['admin_password'],
            )['user'];
        });

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Akun AvanaHR Anda siap. Anda punya '.self::TRIAL_DAYS.' hari trial — pilih paket kapan saja dari menu Langganan.');
    }

    /**
     * Derive a unique slug from the company name (suffixing on collision).
     * Mirrors {@see TenantController::uniqueSlug()}.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'klien';
        $slug = $base;
        $suffix = 1;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
