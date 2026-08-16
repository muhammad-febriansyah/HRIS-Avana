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

it('creates a fixed component without keeping a rupiah nominal on it', function (): void {
    // The nominal belongs to Master Gaji; a figure posted here is dropped so
    // payroll can never pay from a second, unassigned source.
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
    expect($c->basis_value)->toBeNull();
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

it('saves which component a percentage component is a percentage of', function (): void {
    $reference = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.component.store'), [
            'code' => 'TJ-PCT',
            'name' => 'Tunjangan Kinerja',
            'group' => 'penerimaan',
            'calc_basis' => 'percentage',
            'basis_type' => 'fixed',
            'basis_value' => 10,
            'percentage_of_component_id' => $reference->id,
        ])
        ->assertSessionHas('success');

    $c = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-PCT')->firstOrFail();
    expect($c->percentage_of_component_id)->toBe($reference->id);
    expect((float) $c->basis_value)->toBe(10.0);
});

it('drops the percentage reference when the component stops being a percentage', function (): void {
    $reference = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    $component = PayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TJ-WAS-PCT',
        'name' => 'Tunjangan Pindah',
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'status' => 'active',
        'calc_basis' => 'percentage',
        'basis_type' => 'fixed',
        'basis_value' => 10,
        'percentage_of_component_id' => $reference->id,
    ]);

    actingAs($this->admin)
        ->put(route('avana.payroll.komponen.component.update', $component), [
            'name' => 'Tunjangan Pindah',
            'group' => 'penerimaan',
            'calc_basis' => 'fixed',
            'basis_type' => 'fixed',
            'basis_value' => 300000,
        ])
        ->assertSessionHas('success');

    expect($component->fresh()->percentage_of_component_id)->toBeNull();
});

it('refuses a percentage component that points at itself', function (): void {
    $component = PayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TJ-SELF',
        'name' => 'Tunjangan Sendiri',
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'status' => 'active',
        'calc_basis' => 'percentage',
        'basis_type' => 'fixed',
        'basis_value' => 10,
    ]);

    actingAs($this->admin)
        ->put(route('avana.payroll.komponen.component.update', $component), [
            'name' => 'Tunjangan Sendiri',
            'group' => 'penerimaan',
            'calc_basis' => 'percentage',
            'basis_type' => 'fixed',
            'basis_value' => 10,
            'percentage_of_component_id' => $component->id,
        ])
        ->assertSessionHasErrors('percentage_of_component_id');
});

it('refuses a percentage of another percentage', function (): void {
    $other = PayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TJ-PCT-A',
        'name' => 'Tunjangan Persen A',
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'status' => 'active',
        'calc_basis' => 'percentage',
        'basis_type' => 'fixed',
        'basis_value' => 10,
    ]);

    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.component.store'), [
            'code' => 'TJ-PCT-B',
            'name' => 'Tunjangan Persen B',
            'group' => 'penerimaan',
            'calc_basis' => 'percentage',
            'basis_type' => 'fixed',
            'basis_value' => 5,
            'percentage_of_component_id' => $other->id,
        ])
        ->assertSessionHasErrors('percentage_of_component_id');
});
