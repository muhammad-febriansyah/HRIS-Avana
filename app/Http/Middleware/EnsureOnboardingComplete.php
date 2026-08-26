<?php

namespace App\Http\Middleware;

use App\Http\Controllers\CompanyRegistrationController;
use App\Support\OnboardingStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a tenant to the "Mulai" checklist until they've picked a package and
 * saved their company profile — the two things a self-serve signup
 * ({@see CompanyRegistrationController}) deliberately
 * skips to stay fast. Same shape as {@see EnsureSubscriptionActive}:
 * everything closed except the checklist page and the two screens it points
 * to, super admins never locked.
 */
class EnsureOnboardingComplete
{
    /**
     * Route names that stay reachable while onboarding is incomplete.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROUTES = [
        'avana.mulai',
        'avana.perusahaan',
        'avana.perusahaan.profile',
        'avana.langganan',
        'avana.langganan.purchase',
        'avana.langganan.callback',
        'avana.locked',
        'logout',
        'login',
        'home',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->tenant_id === null) {
            return $next($request);
        }

        if ($user->roles()->where('code', 'super_admin')->exists()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            return $next($request);
        }

        if (OnboardingStatus::isComplete($tenant)) {
            // One-way latch: stop checking package/profile on every request
            // for this tenant from now on, so an admin later switching them
            // to "Tanpa Paket" (a legitimate state) can never re-lock them.
            if ($tenant->requires_onboarding) {
                $tenant->update(['requires_onboarding' => false]);
            }

            return $next($request);
        }

        $name = $request->route()?->getName();

        if ($name !== null && in_array($name, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'locked' => true,
                'code' => 'onboarding_incomplete',
                'message' => 'Lengkapi paket langganan dan profil perusahaan sebelum melanjutkan.',
            ], 402);
        }

        return redirect()->route('avana.mulai');
    }
}
