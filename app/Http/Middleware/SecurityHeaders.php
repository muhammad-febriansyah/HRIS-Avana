<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attaches the browser-enforced security headers to every response.
 *
 * The headers are the cheap half of application security: they cost one
 * middleware pass and remove whole classes of attack that no amount of
 * server-side validation can reach — clickjacking, MIME sniffing, referrer
 * leakage of a signed URL to a third party, and script injection.
 *
 * Configuration lives in `config/security.php` so a deployment can widen the
 * policy for a host this application does not know about yet without a code
 * change.
 */
class SecurityHeaders
{
    /**
     * Headers that are meaningful on any response, including JSON and files.
     *
     * @var array<string, string>
     */
    private const UNIVERSAL = [
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::UNIVERSAL as $header => $value) {
            $response->headers->set($header, $value);
        }

        $this->applyReferrerPolicy($response);
        $this->applyHsts($request, $response);

        if ($this->isHtml($response)) {
            $this->applyHtmlHeaders($response);
            $this->applyContentSecurityPolicy($response);
        }

        return $response;
    }

    /**
     * Keep the full URL out of cross-origin requests. Signed document URLs and
     * password-reset links travel in the path; a Referer header carrying one to
     * a font CDN or a map tile host hands that link away.
     */
    private function applyReferrerPolicy(Response $response): void
    {
        $policy = (string) config('security.headers.referrer_policy');

        if ($policy !== '') {
            $response->headers->set('Referrer-Policy', $policy);
        }
    }

    /**
     * Pin the domain to HTTPS. Only over a secure connection and only in
     * production — a browser that receives HSTS from http://localhost will
     * refuse plain HTTP for every other local project on the same host.
     */
    private function applyHsts(Request $request, Response $response): void
    {
        if (! config('security.headers.hsts.enabled') || ! app()->isProduction() || ! $request->isSecure()) {
            return;
        }

        $value = 'max-age='.(int) config('security.headers.hsts.max_age');

        if (config('security.headers.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.headers.hsts.preload')) {
            $value .= '; preload';
        }

        $response->headers->set('Strict-Transport-Security', $value);
    }

    /**
     * Headers that only matter for a rendered document.
     */
    private function applyHtmlHeaders(Response $response): void
    {
        $map = [
            'X-Frame-Options' => (string) config('security.headers.frame_options'),
            'Permissions-Policy' => (string) config('security.headers.permissions_policy'),
            'Cross-Origin-Opener-Policy' => (string) config('security.headers.cross_origin_opener_policy'),
            'Cross-Origin-Resource-Policy' => (string) config('security.headers.cross_origin_resource_policy'),
        ];

        foreach ($map as $header => $value) {
            if ($value !== '') {
                $response->headers->set($header, $value);
            }
        }
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        if (! config('security.csp.enabled')) {
            return;
        }

        $policy = $this->buildPolicy();

        if ($policy === '') {
            return;
        }

        $header = config('security.csp.enforce')
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($header, $policy);
    }

    /**
     * Flatten the configured directives into a policy string, merging the
     * development-only sources when the app is not in production.
     */
    private function buildPolicy(): string
    {
        /** @var array<string, array<int, string>> $directives */
        $directives = config('security.csp.directives', []);

        if (! app()->isProduction()) {
            /** @var array<string, array<int, string>> $extra */
            $extra = config('security.csp.development_directives', []);

            foreach ($extra as $directive => $sources) {
                $directives[$directive] = array_values(array_unique(
                    array_merge($directives[$directive] ?? [], $sources),
                ));
            }
        }

        $parts = [];

        foreach ($directives as $directive => $sources) {
            $sources = array_filter((array) $sources, static fn ($source): bool => is_string($source) && $source !== '');

            if ($sources === []) {
                continue;
            }

            $parts[] = $directive.' '.implode(' ', $sources);
        }

        if ($parts === []) {
            return '';
        }

        $reportUri = config('security.csp.report_uri');

        if (is_string($reportUri) && $reportUri !== '') {
            $parts[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $parts);
    }

    /**
     * A streamed response has no buffered body to inspect, so decide on the
     * declared content type alone.
     */
    private function isHtml(Response $response): bool
    {
        if ($response instanceof StreamedResponse) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        return $contentType === '' || str_contains($contentType, 'text/html');
    }
}
