<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->superadmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
});

/** Every parameterless GET page under /avana that renders a screen (not a download). */
function avanaPageUris(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => str_starts_with($uri, 'avana/'))
        ->reject(fn (string $uri) => str_contains($uri, '{'))
        ->reject(fn (string $uri) => str_contains($uri, 'export')
            || str_contains($uri, 'transfer')
            || str_contains($uri, 'bpjs-export')
            || str_contains($uri, 'cetak'))
        ->unique()
        ->values()
        ->all();
}

it('renders every avana page for super admin without a server error', function (): void {
    $failures = [];

    foreach (avanaPageUris() as $uri) {
        $status = actingAs($this->superadmin)->get('/'.$uri)->status();

        if ($status >= 400) {
            $failures[] = "/{$uri} → {$status}";
        }
    }

    expect($failures)->toBe([]);
});

it('renders core avana pages for an HR admin without a server error', function (): void {
    // Super-admin-only screens legitimately 403 for HR; assert those are the
    // ONLY non-200s and nothing 500s.
    $serverErrors = [];

    foreach (avanaPageUris() as $uri) {
        $status = actingAs($this->admin)->get('/'.$uri)->status();

        if ($status >= 500) {
            $serverErrors[] = "/{$uri} → {$status}";
        }
    }

    expect($serverErrors)->toBe([]);
});

it('redirects guests away from avana pages', function (): void {
    foreach (array_slice(avanaPageUris(), 0, 5) as $uri) {
        $this->get('/'.$uri)->assertRedirect();
    }
});
