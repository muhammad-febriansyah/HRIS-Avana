<?php

use App\Models\Employee;
use App\Models\Payday;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/** The payload of a head-office style payday group. */
function paydayPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'PD-PUSAT',
        'name' => 'Kantor Pusat & Staff',
        'pay_mode' => 'date',
        'pay_day' => 25,
        'cut_off_start_day' => 21,
        'cut_off_end_day' => 20,
        'description' => 'Dibayar tanggal 25',
        'is_active' => true,
    ], $overrides);
}

it('renders the Mapping Payday screen', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.payday'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-payday/index', false)
            ->has('paydays')
            ->has('employees')
            ->etc());
});

it('creates a payday group with its cut-off window', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), paydayPayload())
        ->assertSessionHas('success');

    $payday = Payday::forTenant($this->tenant->id)->where('code', 'PD-PUSAT')->firstOrFail();

    expect($payday->payLabel())->toBe('Tanggal 25');
    expect($payday->cutOffLabel())->toBe('21 – 20 bulan berjalan');
});

it('clears the pay day when the group is paid at month end', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), paydayPayload([
            'code' => 'PD-OPS',
            'pay_mode' => 'end_of_month',
            'pay_day' => 30,
        ]))
        ->assertSessionHas('success');

    $payday = Payday::forTenant($this->tenant->id)->where('code', 'PD-OPS')->firstOrFail();

    expect($payday->pay_day)->toBeNull();
    expect($payday->payLabel())->toBe('Akhir bulan');
    expect($payday->payDateFor(Carbon\Carbon::parse('2026-02-10'))->toDateString())->toBe('2026-02-28');
});

it('requires a pay day when the group pays on a fixed date', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), paydayPayload(['pay_day' => null]))
        ->assertSessionHasErrors('pay_day');
});

it('requires both ends of a cut-off window', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), paydayPayload(['cut_off_end_day' => null]))
        ->assertSessionHasErrors('cut_off_end_day');
});

it('refuses a duplicate code inside the tenant', function (): void {
    actingAs($this->admin)->post(route('avana.payroll.payday.store'), paydayPayload());

    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), paydayPayload())
        ->assertSessionHasErrors('code');
});

it('maps and unmaps employees', function (): void {
    actingAs($this->admin)->post(route('avana.payroll.payday.store'), paydayPayload());
    $payday = Payday::forTenant($this->tenant->id)->firstOrFail();
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.payroll.payday.assign'), [
            'payday_id' => $payday->id,
            'employee_ids' => [$employee->id],
        ])
        ->assertSessionHas('success');

    expect($employee->fresh()->payday_id)->toBe($payday->id);

    actingAs($this->admin)
        ->post(route('avana.payroll.payday.assign'), [
            'payday_id' => null,
            'employee_ids' => [$employee->id],
        ])
        ->assertSessionHas('success');

    expect($employee->fresh()->payday_id)->toBeNull();
});

it('keeps a payday group out of reach of another tenant', function (): void {
    actingAs($this->admin)->post(route('avana.payroll.payday.store'), paydayPayload());
    $payday = Payday::forTenant($this->tenant->id)->firstOrFail();

    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active']);
    $intruder = User::factory()->create(['tenant_id' => $other->id]);

    actingAs($intruder)
        ->delete(route('avana.payroll.payday.destroy', $payday))
        ->assertForbidden();

    expect(Payday::find($payday->id))->not->toBeNull();
});
