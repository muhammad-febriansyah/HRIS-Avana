<?php

use App\Http\Middleware\CaptureReferral;
use App\Http\Middleware\EnsureAppIsNotDown;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureFreshToken;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveActiveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'token.fresh' => EnsureFreshToken::class,
            'feature' => EnsureFeature::class,
        ]);

        $middleware->web(prepend: [
            EnsureAppIsNotDown::class,
            CaptureReferral::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            ResolveActiveTenant::class,
            EnsureSubscriptionActive::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // A lapsed tenant is locked out of the mobile API too, not just the web
        // app — otherwise the phone keeps working after the web app stops.
        $middleware->api(prepend: [
            EnsureAppIsNotDown::class,
        ]);

        $middleware->api(append: [
            EnsureSubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // A file bigger than post_max_size is dropped by PHP before Laravel
        // ever validates it, which surfaces as a bare 413 page. Say what
        // actually happened — the tester read that page as "upload KTP error"
        // with nothing to act on.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $limit = ini_get('post_max_size') ?: '2M';
            $message = "Ukuran berkas melebihi batas server ({$limit}). Perkecil file (mis. kompres foto) lalu coba lagi.";

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return back()->withErrors(['file' => $message]);
        });
    })->create();
