<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a "page_view" activity row for every full-page GET an authenticated
 * user makes inside `/avana/*`. Inertia's own partial reloads (polling,
 * prop-refresh) are skipped so the log reflects navigation, not chatter.
 */
class LogPageActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request)) {
            ActivityLogger::log('page_view', 'Membuka halaman /'.$request->path());
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (! $request->isMethod('get') || ! auth()->check()) {
            return false;
        }

        // Partial reloads (Inertia prop refresh, polling) revisit the same
        // page the user is already on — not a new navigation.
        if ($request->header('X-Inertia-Partial-Component') !== null) {
            return false;
        }

        return true;
    }
}
