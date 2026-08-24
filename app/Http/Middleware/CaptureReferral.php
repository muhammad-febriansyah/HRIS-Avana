<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ReferralLeadController;
use App\Models\Partner;
use App\Models\ReferralClick;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attributes a visit to a referral partner from a `?ref=CODE` query string.
 *
 * Last-click wins: a valid `ref` always overwrites whatever attribution the
 * visitor already carried, so the partner whose link they followed most
 * recently is the one credited if they end up submitting the "Daftar
 * Perusahaan" inquiry. The cookie is what {@see ReferralLeadController}
 * reads back when that form is eventually submitted, so it needs to survive
 * a browsing session, not just one request.
 */
class CaptureReferral
{
    /**
     * How long attribution survives without another click.
     */
    private const COOKIE_DAYS = 60;

    public const COOKIE_NAME = 'avana_ref';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('ref');

        if (! is_string($code) || $code === '') {
            return $next($request);
        }

        $partner = Partner::query()->active()->where('code', $code)->first();

        if ($partner === null) {
            return $next($request);
        }

        ReferralClick::create([
            'partner_id' => $partner->id,
            'ip_address' => $request->ip(),
            'user_agent' => (string) substr((string) $request->userAgent(), 0, 255),
            'landing_path' => $request->path(),
        ]);

        // Queued, not set directly on the response: this middleware runs
        // OUTSIDE EncryptCookies (it is prepended ahead of the framework's
        // default web stack), so a cookie attached after `$next()` returns
        // would skip encryption entirely and fail to decrypt on the next
        // request. Queuing before `$next()` lets AddQueuedCookiesToResponse
        // — and then EncryptCookies, both further inward — handle it in the
        // right order.
        Cookie::queue(self::COOKIE_NAME, $partner->code, self::COOKIE_DAYS * 24 * 60);

        return $next($request);
    }
}
