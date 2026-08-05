<?php

use App\Models\Employee;
use App\Models\Feature;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use App\Support\FeatureGate;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/** Switch one tenant feature off (or back on) by its code. */
function setFeature(Tenant $tenant, string $code, bool $enabled): void
{
    $tenant->features()->updateOrCreate(
        ['feature_id' => Feature::where('code', $code)->value('id')],
        ['is_enabled' => $enabled],
    );
}

it('drops the Lembur tab and closes its endpoints when overtime is off', function (): void {
    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.overtime', true));

    setFeature($this->tenant, 'overtime', false);

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.overtime', false));

    $employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
        ])
        ->assertForbidden();

    // Setup Lembur lives under the Payroll menu, so only this toggle closes it.
    actingAs($this->admin)->get(route('avana.payroll.lembur'))->assertForbidden();
});

it('drops the WFH tab and closes its endpoints when wfh is off', function (): void {
    setFeature($this->tenant, 'wfh', false);

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.wfh', false));

    $employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('keeps the Cuti page open when only the overtime feature is off', function (): void {
    setFeature($this->tenant, 'overtime', false);

    actingAs($this->admin)->get(route('avana.cuti'))->assertOk();
});

it('hides the PPh 21 half and closes its writes when pph21 is off', function (): void {
    setFeature($this->tenant, 'pph21', false);

    actingAs($this->admin)
        ->get(route('avana.payroll.konfigurasi'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('features.pph21', false)
            ->where('features.bpjs', true));

    actingAs($this->admin)
        ->post(route('avana.payroll.konfigurasi.ptkp.store'), [
            'ptkp_status' => 'TK/0',
            'year' => 2026,
            'amount' => 54000000,
        ])
        ->assertForbidden();
});

it('closes the BPJS & Pajak screen when both of its features are off', function (): void {
    setFeature($this->tenant, 'bpjs', false);
    setFeature($this->tenant, 'pph21', false);

    actingAs($this->admin)->get(route('avana.payroll.konfigurasi'))->assertForbidden();
});

it('leaves no feature toggle without something to switch', function (): void {
    $ownedByMenu = collect(AvanaNav::menuRows($this->tenant->id))
        ->pluck('feature')
        ->filter()
        ->unique();

    $covered = $ownedByMenu->merge(array_keys(FeatureGate::TAB_FEATURES));

    // Every row in the Kelola Fitur catalogue has to reach a menu leaf of its
    // own or a tab this gate closes — otherwise its switch writes to the
    // database and changes nothing the tenant can see.
    expect(Feature::pluck('code')->diff($covered)->values()->all())->toBe([]);
});
