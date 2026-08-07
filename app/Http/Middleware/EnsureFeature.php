<?php

namespace App\Http\Middleware;

use App\Models\Feature;
use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes a mobile API route whose tenant feature is switched off.
 *
 * The phone's menus already drop what a tenant does not run, but a build that
 * predates the change — or a screen reached from a notification — can still ask
 * for a module nobody enabled. Gating a whole route group keeps that answer one
 * sentence rather than a page of empty data.
 */
class EnsureFeature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $code): Response
    {
        FeatureGate::ensure($request->user(), $code, $this->message($code));

        return $next($request);
    }

    /**
     * "Fitur Ruang Kita tidak aktif…" reads better on a phone than the feature
     * code does, so the human name is looked up when there is one.
     */
    private function message(string $code): string
    {
        $name = Feature::where('code', $code)->value('name');

        return $name !== null
            ? 'Fitur '.$name.' tidak aktif untuk perusahaan Anda.'
            : 'Fitur ini tidak aktif untuk perusahaan Anda.';
    }
}
