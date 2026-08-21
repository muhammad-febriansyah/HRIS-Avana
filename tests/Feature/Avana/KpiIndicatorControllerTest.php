<?php

use App\Models\KpiIndicator;
use App\Models\Permission;
use App\Models\Role;
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

it('renders the indicator index scoped to the current tenant', function (): void {
    KpiIndicator::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Produktivitas',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    KpiIndicator::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Indikator Asing',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.kinerja.indikator'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kinerja/kpi-indicators', false)
            ->has('indicators', 1)
            ->has('directions'));
});

it('creates an indicator scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.indikator.store'), [
            'name' => 'Kualitas Layanan',
            'unit' => '%',
            'direction' => 'higher_better',
            'category' => 'Kualitas',
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    $indicator = KpiIndicator::where('name', 'Kualitas Layanan')->firstOrFail();

    expect($indicator->tenant_id)->toBe($this->tenant->id);
    expect($indicator->direction)->toBe('higher_better');
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.indikator.store'), [
            'name' => '',
            'direction' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'direction']);
});

it('updates an indicator', function (): void {
    $indicator = KpiIndicator::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Lama',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.kinerja.indikator.update', $indicator), [
            'name' => 'Baru',
            'direction' => 'lower_better',
            'is_active' => false,
        ])
        ->assertSessionHas('success');

    $indicator->refresh();

    expect($indicator->name)->toBe('Baru');
    expect($indicator->direction)->toBe('lower_better');
    expect($indicator->is_active)->toBeFalse();
});

it('deletes an indicator', function (): void {
    $indicator = KpiIndicator::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Hapus Saya',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.indikator.destroy', $indicator))
        ->assertSessionHas('success');

    expect(KpiIndicator::find($indicator->id))->toBeNull();
});

it('returns 404 when updating an indicator from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = KpiIndicator::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Asing',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.kinerja.indikator.update', $foreign), [
            'name' => 'Diubah',
            'direction' => 'higher_better',
        ])
        ->assertNotFound();
});

it('enforces performance permissions on indicator management', function (): void {
    $role = Role::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'performance-viewer-kpi',
        'name' => 'Performance Viewer',
        'is_system' => false,
    ]);
    $role->permissions()->syncWithoutDetaching(
        Permission::where('code', 'performance.view')->pluck('id'),
    );

    $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $viewer->roles()->sync([$role->id]);

    actingAs($viewer)
        ->get(route('avana.kinerja.indikator'))
        ->assertOk();

    actingAs($viewer)
        ->post(route('avana.kinerja.indikator.store'), [
            'name' => 'Tidak Boleh',
            'direction' => 'higher_better',
        ])
        ->assertForbidden();
});
