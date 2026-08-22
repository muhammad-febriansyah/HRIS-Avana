<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * Guards the failure mode behind a production outage: `.env` was mode 600 owned
 * by the deploy user, PHP-FPM could not read it, every `env()` returned null,
 * and Laravel's stock `env('DB_CONNECTION', 'sqlite')` quietly pointed a
 * MySQL-only application at a SQLite file that did not exist. Cache, session,
 * and queue all ride on that connection, so the whole app died on a message
 * that named neither the real cause nor the real database.
 */

/**
 * Call the provider's environment guard in isolation — booting the whole
 * provider would re-register observers and event listeners on the live app.
 */
function assertEnvironmentIsLoaded(): void
{
    $provider = new AppServiceProvider(app());

    (fn () => $this->assertEnvironmentIsLoaded())->call($provider);
}

it('has no silent database fallback configured', function (): void {
    // Asserted against the source, not the resolved value: phpunit.xml sets
    // DB_CONNECTION, so the resolved config can never show the fallback that
    // only appears on a machine whose environment failed to load.
    $source = file_get_contents(config_path('database.php'));

    expect($source)->toMatch("/'default' => env\\('DB_CONNECTION'\\),/");
    expect($source)->not->toMatch("/'default' => env\\('DB_CONNECTION', /");
});

it('refuses to boot with a legible error when the environment never loaded', function (): void {
    $original = config('database.default');

    try {
        Config::set('database.default', null);

        expect(fn () => assertEnvironmentIsLoaded())
            ->toThrow(RuntimeException::class, 'DB_CONNECTION is not set');
    } finally {
        // Restore before teardown: RefreshDatabase rolls back on the default
        // connection, and it cannot roll back one that no longer resolves.
        Config::set('database.default', $original);
    }
});

it('passes the environment check when the connection is configured', function (): void {
    expect(config('database.default'))->toBe('sqlite'); // set by phpunit.xml

    expect(fn () => assertEnvironmentIsLoaded())->not->toThrow(RuntimeException::class);
});
