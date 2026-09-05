<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders the branded error page for a missing URL', function (): void {
    get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', false)
            ->where('status', 404));
});

it('renders the branded error page for a forbidden screen', function (): void {
    $this->seed(AvanaDemoSeeder::class);

    // A plain employee has no business on the access-control screen.
    $employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($employee)
        ->get(route('avana.hak-akses'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', false)
            ->where('status', 403)
            // No reason was written for this one, so the page keeps its
            // generic wording.
            ->where('detail', null));
});

it('shows the reason the application gave instead of the generic 403 wording', function (): void {
    Route::middleware('web')->get('/__test-403', function (): void {
        abort(403, 'Kalibrasi harus dilakukan oleh pihak lain.');
    });

    get('/__test-403')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', false)
            ->where('detail', 'Kalibrasi harus dilakukan oleh pihak lain.'));
});

it('does not leak framework wording from a 404 onto the error page', function (): void {
    get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->where('detail', null));
});

it('carries the shared branding into the error page', function (): void {
    get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('error', false)
            ->has('website')
            ->has('auth'));
});

it('keeps API failures as JSON rather than an HTML error page', function (): void {
    // A JSON body, not the branded HTML page the mobile app cannot read.
    $this->getJson('/api/v1/me/dashboard')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('has a dependency-free Blade fallback for every handled status', function (): void {
    foreach ([403, 404, 419, 429, 500, 503] as $status) {
        $html = view("errors.{$status}")->render();

        expect($html)
            ->toContain('ERROR '.$status)
            ->toContain('sebutkan kode');
    }
});
