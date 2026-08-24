<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Public\ReferralLeadController;
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

        $response = $next($request);

        $response->headers->setCookie(
            Cookie::make(self::COOKIE_NAME, $partner->code, self::COOKIE_DAYS * 24 * 60),
        );

        return $response;
    }
}
