<?php

use App\Models\PayrollComponent;
use App\Models\PayrollFormula;
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

it('renders master komponen with components and formulas', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.komponen'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-komponen/index', false)
            ->has('components')
            ->has('formulas')
            ->has('componentOptions')
            ->has('formulaOptions')
            ->has('mappingOptions'));
});

it('creates a component with a fixed dasar perhitungan', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.component.store'), [
            'code' => 'TJ-FIX',
            'name' => 'Tunjangan Fixed',
            'group' => 'penerimaan',
            'calc_basis' => 'fixed',
            'basis_type' => 'fixed',
            'basis_value' => 450000,
        ])
        ->assertSessionHas('success');

    $c = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-FIX')->firstOrFail();
    expect($c->basis_type)->toBe('fixed');
    expect((float) $c->basis_value)->toBe(450_000.0);
});

it('creates a taxable earning component in the penerimaan group', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.component.store'), [
            'code' => 'TJ-KHR',
            'name' => 'Tunjangan Kehadiran',
            'group' => 'penerimaan',
            'calc_basis' => 'per_present_day',
            'is_taxable' => true,
            'show_on_slip' => true,
        ])
        ->assertSessionHas('success');

    $c = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-KHR')->firstOrFail();
    expect($c->component_group)->toBe('penerimaan');
    expect($c->type)->toBe('earning');
    expect($c->is_taxable)->toBeTrue();
});

it('creates a formula and adds a kombinasi komponen item', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.formula.store'), ['name' => 'Gaji Kotor'])
        ->assertSessionHas('success');

    $formula = PayrollFormula::forTenant($this->tenant->id)->where('name', 'Gaji Kotor')->firstOrFail();
    $component = PayrollComponent::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.formula.item.store', $formula), [
            'tipe' => 'penerimaan',
            'payroll_component_id' => $component->id,
            'operator' => '*',
            'nilai' => 1,
            'prorate' => true,
        ])
        ->assertSessionHas('success');

    expect($formula->items()->count())->toBe(1);
});
