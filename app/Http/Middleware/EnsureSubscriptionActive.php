<?php

namespace App\Http\Middleware;

use App\Support\SubscriptionStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a tenant out of the product once its subscription term has run out.
 *
 * Everything is closed — every role, every screen, the mobile API included —
 * except the handful of paths needed to get out of the lock: the renewal page
 * and its payment callback, the lock notice itself, and signing out. Paying
 * moves the end date, and the very next request is unlocked again.
 *
 * Super admins are never locked: platform staff must be able to work on a
 * lapsed client, including while viewing as that tenant.
 */
class EnsureSubscriptionActive
{
    /**
     * Route names that stay reachable while locked.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROUTES = [
        'avana.locked',
        'avana.langganan',
        'avana.langganan.purchase',
        'avana.langganan.callback',
        'logout',
        'login',
        'home',
    ];

    /**
     * API path suffixes that stay reachable while locked, so the mobile app can
     * still authenticate and be told why it is stuck.
     *
     * @var array<int, string>
     */
    private const ALLOWED_API_PATHS = [
        'api/v1/app-config',
        'api/v1/onboarding-slides',
        'api/v1/pakasir/webhook',
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/refresh',
        'api/v1/auth/me',
        'api/v1/subscription',
    ];

    /**
     * Handle an incoming request.
     *
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

        if ($tenant === null || ! SubscriptionStatus::isExpired($tenant)) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $notice = SubscriptionStatus::forTenant($tenant);
        $message = 'Masa langganan AvanaHR berakhir pada '.($notice['end_date_label'] ?? '-')
            .'. Perpanjang langganan untuk membuka kembali semua fitur.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'locked' => true,
                'code' => 'subscription_expired',
                'message' => $message,
                'end_date' => $notice['end_date'] ?? null,
            ], 402);
        }

        return redirect()->route('avana.locked')->with('error', $message);
    }

    /**
     * Whether this request targets one of the few paths a locked tenant keeps.
     */
    private function isAllowed(Request $request): bool
    {
        if ($request->is(...self::ALLOWED_API_PATHS)) {
            return true;
        }

        $name = $request->route()?->getName();

        return $name !== null && in_array($name, self::ALLOWED_ROUTES, true);
    }
}
