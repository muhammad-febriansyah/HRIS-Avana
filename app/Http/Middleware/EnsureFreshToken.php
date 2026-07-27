<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the per-user token version (`users.token_version`) on JWT requests.
 * A change of password or a "sign out of all devices" bumps the version; any
 * token carrying a stale `tv` claim is rejected, which is how the stateless JWT
 * layer revokes outstanding tokens en masse. Tokens whose `tv` still matches
 * the user's current version pass.
 */
class EnsureFreshToken
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $token = $request->bearerToken();

        // Both must be present: `setToken(null)` throws a TokenInvalidException,
        // which surfaces as a 500 instead of the 401 the caller deserves. In
        // production a resolved user always arrives with its bearer header, so
        // this only bites when the guard was primed some other way.
        if ($user !== null && $token !== null) {
            $payload = auth('api')->setToken($token)->getPayload();
            $tokenVersion = (int) ($payload->get('tv') ?? 0);

            if ($tokenVersion !== (int) $user->token_version) {
                return response()->json([
                    'message' => 'Sesi telah berakhir. Silakan masuk kembali.',
                ], 401);
            }
        }

        return $next($request);
    }
}
