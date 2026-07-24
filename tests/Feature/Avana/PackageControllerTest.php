<?php

use App\Models\Package;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenantAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('lists the pricing tiers seeded into the DB', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.paket'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/paket/index', false)
            ->has('cycles')
            ->where('packages', fn ($packages) => collect($packages)->contains('code', 'hc_starter')));

    $tier = Package::where('code', 'hc_growth')->firstOrFail();
    expect($tier->tagline)->toBe('Professional')
        ->and($tier->feature_list)->toContain('Contract Management')
        ->and($tier->is_popular)->toBeTrue();
});

it('creates a package with a feature list', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.paket.store'), [
            'name' => 'HC Custom',
            'tagline' => 'Khusus',
            'price' => 4200000,
            'billing_cycle' => 'monthly',
            'max_employees' => 150,
            'feature_list' => ['Payroll Management', '', '  Mobile Apps  '],
            'is_active' => true,
            'is_popular' => false,
        ])
        ->assertSessionHas('success');

    $pkg = Package::where('name', 'HC Custom')->firstOrFail();
    expect($pkg->code)->toBe('hc_custom')
        ->and($pkg->feature_list)->toBe(['Payroll Management', 'Mobile Apps']);
});

it('updates a package and keeps its code', function (): void {
    $pkg = Package::where('code', 'hc_starter')->firstOrFail();

    actingAs($this->superAdmin)
        ->put(route('avana.paket.update', $pkg), [
            'name' => 'HC Starter Plus',
            'price' => 2750000,
            'billing_cycle' => 'monthly',
            'feature_list' => ['Database Employee'],
            'is_active' => true,
            'is_popular' => false,
        ])
        ->assertSessionHas('success');

    $pkg->refresh();
    expect($pkg->name)->toBe('HC Starter Plus')
        ->and($pkg->code)->toBe('hc_starter');
});

it('deletes a package', function (): void {
    $pkg = Package::create(['code' => 'temp', 'name' => 'Temp', 'price' => 0, 'billing_cycle' => 'monthly']);

    actingAs($this->superAdmin)
        ->delete(route('avana.paket.destroy', $pkg))
        ->assertSessionHas('success');

    expect(Package::find($pkg->id))->toBeNull();
});

it('forbids a non-super-admin from managing packages', function (): void {
    actingAs($this->tenantAdmin)->get(route('avana.paket'))->assertForbidden();
    actingAs($this->tenantAdmin)->post(route('avana.paket.store'), [
        'name' => 'X',
        'price' => 0,
        'billing_cycle' => 'monthly',
    ])->assertForbidden();
});
