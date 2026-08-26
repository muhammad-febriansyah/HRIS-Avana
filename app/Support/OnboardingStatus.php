<?php

namespace App\Support;

use App\Http\Controllers\CompanyRegistrationController;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Models\Tenant;

/**
 * Whether a tenant has finished the minimum setup a self-serve signup skips
 * on purpose — {@see CompanyRegistrationController}'s
 * wizard collects no package and no company profile, so signup stays a two
 * -minute form. A package and a saved company profile are what the rest of
 * the product assumes exist (billing needs a package, documents/branding
 * need a company name) — {@see EnsureOnboardingComplete}
 * is what actually enforces finishing both before anything else opens up.
 *
 * Not memoised on purpose: unlike {@see SubscriptionStatus}, this runs on
 * every request for every tenant user (not just an occasional lapsed one),
 * and a per-process cache would serve a stale "still incomplete" verdict for
 * the rest of that process after the tenant finishes setup mid-session.
 */
final class OnboardingStatus
{
    /**
     * @return array{needs_package: bool, needs_profile: bool, complete: bool}
     */
    public static function resolve(Tenant $tenant): array
    {
        // Never gated in the first place — every tenant created before this
        // flag existed, and every admin-created one ("Tanpa Paket" included).
        if (! $tenant->requires_onboarding) {
            return ['needs_package' => false, 'needs_profile' => false, 'complete' => true];
        }

        $needsPackage = $tenant->package_id === null;
        $needsProfile = $tenant->company()->doesntExist();

        return [
            'needs_package' => $needsPackage,
            'needs_profile' => $needsProfile,
            'complete' => ! $needsPackage && ! $needsProfile,
        ];
    }

    public static function isComplete(Tenant $tenant): bool
    {
        return self::resolve($tenant)['complete'];
    }
}
