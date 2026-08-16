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

it('names the required fields blocking the next step', function () {
    // A greyed-out Lanjut with nothing to explain it left people scanning the
    // form for whichever box was empty — Status Kepegawaian sits below the
    // fold on a short window, so it was the usual culprit.
    actingAs($this->admin);

    $page = visit('/avana/employees/create');

    $page->assertSee('Lengkapi dulu: Nama Lengkap, NIK (KTP)');

    $page->fill('#full_name', 'Rahmat Uji');
    // Every personal field is required now, so naming the next gap is what
    // keeps the greyed-out Lanjut explainable.
    $page->assertSee('Lengkapi dulu: NIK (KTP)')
        ->assertDontSee('Lengkapi dulu: Nama Lengkap')
        ->assertNoJavascriptErrors();
});
