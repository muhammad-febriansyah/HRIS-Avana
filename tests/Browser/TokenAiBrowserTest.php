<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the per-role token allocation to a tenant admin', function () {
    $this->seed(AvanaDemoSeeder::class);
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($admin);

    $page = visit('/avana/token-ai');

    $page->assertSee('Token AI')
        ->assertSee('Beli Token')
        ->assertSee('Alokasi per Role')
        ->assertSee('Admin Tenant / HR')
        ->assertSee('Manager')
        ->assertSee('Finance')
        ->assertSee('Karyawan')
        ->assertNoJavascriptErrors();
});
