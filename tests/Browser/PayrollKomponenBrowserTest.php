<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('takes component nominal from Master Gaji instead of the Master Komponen modal', function () {
    $this->seed(AvanaDemoSeeder::class);
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($admin);

    $page = visit('/avana/payroll/komponen');

    $page->click('Tambah Komponen')
        ->assertSee('Nama Komponen')
        ->assertSee('Tipe Perhitungan')
        ->assertDontSee('Nilai / Nominal (Rp)')
        ->assertNoJavascriptErrors();
});
