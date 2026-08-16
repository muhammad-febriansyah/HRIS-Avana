<?php

use App\Models\Employee;
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

it('explains a birth date the server would refuse instead of letting the save look ignored', function () {
    // Rows typed before the minimum-age rule carry dates the update now
    // rejects. The rejection used to arrive only after a submit, so an admin
    // who came to change nothing but the role read it as a dead Simpan button.
    $employee = Employee::where('tenant_id', $this->admin->tenant_id)->firstOrFail();
    $employee->update(['birth_date' => today()->toDateString()]);

    actingAs($this->admin);

    $page = visit('/avana/employees/'.$employee->getRouteKey().'/edit');

    $page->assertSee('Umur minimal 17 tahun.')
        ->assertSee('Tanggal Lahir (umur minimal 17 tahun)')
        ->assertNoJavascriptErrors();
});

it('answers Lanjut with what is wrong on the personal step', function () {
    // Validation that only speaks at the end of the wizard makes a save look
    // ignored: the admin is three steps away from the field that caused it.
    actingAs($this->admin);

    $page = visit('/avana/employees/create');

    $page->click('Lanjut');

    $page->assertSee('Nama lengkap wajib diisi.')
        ->assertSee('NIK wajib diisi.')
        ->assertSee('Identitas sesuai KTP')
        ->assertNoJavascriptErrors();
});

it('stops a NIK that another employee already carries', function () {
    $taken = Employee::where('tenant_id', $this->admin->tenant_id)->firstOrFail();
    $taken->update(['nik' => '3201010101900007']);

    actingAs($this->admin);

    $page = visit('/avana/employees/create');

    $page->fill('#full_name', 'Kembar NIK')
        ->fill('#nik', '3201010101900007')
        ->fill('#email', 'kembar.nik@example.test')
        ->fill('#phone', '081234567890')
        ->fill('#birth_place', 'Bandung');

    $page->click('Lanjut');

    $page->assertSee('sudah dipakai karyawan lain')
        ->assertNoJavascriptErrors();
});
