<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the theme editor with presets and a live preview', function () {
    actingAs($this->admin);

    $page = visit('/avana/tampilan');

    $page->assertSee('Tampilan & Tema')
        ->assertSee('Preset')
        ->assertSee('Midnight')
        ->assertSee('Latar Sidebar')
        ->assertSee('Pratinjau')
        ->assertSee('Simpan Tema')
        ->assertNoJavascriptErrors();
});

it('shows the different tenant roles on the hak akses screen', function () {
    actingAs($this->admin);

    $page = visit('/avana/hak-akses');

    $page->assertSee('Hak Akses & Peran')
        ->assertSee('Buat Role Kustom')
        ->assertSee('Admin Tenant / HR')
        ->assertSee('Manager')
        ->assertSee('Finance')
        ->assertSee('Karyawan')
        ->assertNoJavascriptErrors();
});
