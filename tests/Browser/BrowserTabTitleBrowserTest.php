<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->superadmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
});

it('suffixes a tenant tab with the company, not the platform', function () {
    actingAs($this->admin);

    $page = visit('/avana/organisasi');

    expect($page->script('document.title'))
        ->toContain('PT Nusantara Jaya')
        ->not->toContain('AvanaHR');

    $page->assertNoJavascriptErrors();
});

it('keeps the platform name on the super admin side', function () {
    actingAs($this->superadmin);

    $page = visit('/dashboard');

    expect($page->script('document.title'))->toContain('AvanaHR');

    $page->assertNoJavascriptErrors();
});

it('carries the suffix across a client-side visit', function () {
    // The suffix is derived from the page Inertia hands the title callback, so
    // a visit that never reloads the document must still retitle the tab.
    // Clicked by href, not label, so renaming a menu entry cannot break this.
    actingAs($this->admin);

    $page = visit('/dashboard');
    $page->click('a[href="/avana/pengumuman"]');

    expect($page->script('document.title'))->toContain('PT Nusantara Jaya');
});
