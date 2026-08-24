<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the whole /mitra portal to a signed-in referral partner. Mirrors
 * EnsureAvanaAccess's outright block of a partner from /avana, in reverse —
 * this is the ONLY area a partner login can reach.
 */
class EnsurePartner
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $user->loadMissing('roles', 'partner');

        abort_unless($user->roles->contains(fn ($role): bool => $role->code === 'partner'), 403);
        abort_if($user->partner === null, 403);

        return $next($request);
    }
}
