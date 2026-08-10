<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the whole app when MAINTENANCE_MODE=true, no server access required.
 *
 * `php artisan down` needs a shell on the box; this reads a plain .env flag so
 * maintenance can be toggled from wherever the env is edited (a panel, a
 * deploy script) and still render the same branded 503 page Laravel's own
 * down command uses (resources/views/errors/503.blade.php).
 */
class EnsureAppIsNotDown
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.maintenance_mode')) {
            return $next($request);
        }

        $secret = config('app.maintenance_secret');

        if ($secret !== null && $request->query('secret') === $secret) {
            return $next($request);
        }

        abort(503);
    }
}
