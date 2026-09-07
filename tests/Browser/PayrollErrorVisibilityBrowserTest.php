<?php

use App\Models\PayrollPeriod;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('shows why an approval was refused inside the dialog that asked for it', function () {
    actingAs($this->admin);

    $period = PayrollPeriod::create([
        'tenant_id' => $this->admin->tenant_id,
        'code' => '2026-10',
        'name' => 'Oktober 2026',
        'type' => 'bulanan',
        'cycle' => 'monthly',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'pay_date' => '2026-10-25',
        'status' => 'draft',
    ]);

    post(route('avana.payroll.run'), ['payroll_period_id' => $period->id])
        ->assertSessionHasNoErrors();

    // Editing a component after the run is what makes the figures on screen
    // stale, and a stale run may not be approved. The stamp has to land clearly
    // after computed_at — a touch in the same second is not "after".
    DB::table('payroll_components')
        ->where('tenant_id', $this->admin->tenant_id)
        ->update(['updated_at' => now()->addMinutes(5)]);

    $page = visit('/avana/payroll?period='.$period->id);

    $page->click('Setujui')
        ->click('Setujui')
        ->assertSee('Ada perubahan pada')
        ->assertSee('Jalankan ulang perhitungan sebelum menyetujui.')
        ->assertNoJavascriptErrors();
});

it('names the field a rejected payday group tripped on', function () {
    actingAs($this->admin);

    $page = visit('/avana/payroll/payday');

    // A cut-off end with no start: the server rejects it, and the reason has to
    // land under the field rather than in a toast that names nothing.
    $page->fill('input[placeholder="PD-PUSAT"]', 'PD-UJI')
        ->fill('input[placeholder="Kantor Pusat & Staff"]', 'Uji Cut-off')
        ->fill('[data-test="payday-cut-off-end"]', '20')
        ->click('Simpan')
        ->assertSee('Cut-off harus punya tanggal awal dan akhir.')
        ->assertNoJavascriptErrors();
});
