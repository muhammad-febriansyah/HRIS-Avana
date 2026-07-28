<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
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
            ->where('status', 403));
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
