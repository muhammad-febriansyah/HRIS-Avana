<?php

use App\Models\Feature;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->superAdmin->tenant_id);
});

it('renders the klien index for a super admin with tenants, packages and features', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.klien'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/klien/index', false)
            ->has('tenants.data')
            ->has('tenants.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('slug')
                ->has('status')
                ->has('package')
                ->has('users_count')
                ->has('employees_count')
                ->has('branches_count')
                ->has('feature_codes')
                ->etc())
            ->has('tenants.meta.total')
            ->has('packages')
            ->has('features')
            ->has('filters'));
});

it('forbids an admin_tenant_hr from viewing the klien index', function (): void {
    actingAs($this->admin)
        ->get(route('avana.klien'))
        ->assertForbidden();
});

it('forbids an admin_tenant_hr from creating a tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Tidak Boleh',
            'slug' => 'pt-tidak-boleh',
        ])
        ->assertForbidden();

    expect(Tenant::where('slug', 'pt-tidak-boleh')->exists())->toBeFalse();
});

it('lets a super admin create a tenant and enables every feature by default', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Maju Bersama',
            'company_name' => 'PT Maju Bersama Tbk',
            'slug' => 'maju-bersama',
            'status' => 'trial',
            'max_users' => 25,
            'max_employees' => 500,
            'max_branches' => 5,
        ])
        ->assertSessionHas('success');

    $tenant = Tenant::where('slug', 'maju-bersama')->firstOrFail();

    expect($tenant->name)->toBe('PT Maju Bersama');
    expect($tenant->status)->toBe('trial');
    expect((int) $tenant->max_users)->toBe(25);

    $featureCount = Feature::count();

    expect($tenant->features()->count())->toBe($featureCount);
    expect($tenant->features()->where('is_enabled', true)->count())->toBe($featureCount);
});

it('auto-derives a unique slug from the name when none is given', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Cahaya Abadi',
            'status' => 'active',
        ])
        ->assertSessionHas('success');

    expect(Tenant::where('slug', 'pt-cahaya-abadi')->exists())->toBeTrue();
});

it('validates that the tenant name is required', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => '',
        ])
        ->assertSessionHasErrors('name');
});

it('rejects a duplicate slug', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Duplikat',
            'slug' => $this->tenant->slug,
        ])
        ->assertSessionHasErrors('slug');
});

it('lets a super admin update a tenant', function (): void {
    actingAs($this->superAdmin)
        ->put(route('avana.klien.update', $this->tenant), [
            'name' => 'PT Nusantara Jaya Group',
            'slug' => $this->tenant->slug,
            'status' => 'suspended',
            'max_users' => 150,
        ])
        ->assertSessionHas('success');

    $this->tenant->refresh();

    expect($this->tenant->name)->toBe('PT Nusantara Jaya Group');
    expect($this->tenant->status)->toBe('suspended');
    expect((int) $this->tenant->max_users)->toBe(150);
});

it('toggles a feature on and off for a tenant', function (): void {
    $feature = Feature::firstOrFail();

    // Seeded tenant starts with every feature enabled.
    expect((bool) $this->tenant->features()->where('feature_id', $feature->id)->value('is_enabled'))->toBeTrue();

    actingAs($this->superAdmin)
        ->post(route('avana.klien.feature.toggle', $this->tenant), [
            'feature_id' => $feature->id,
        ])
        ->assertSessionHas('success');

    expect((bool) $this->tenant->features()->where('feature_id', $feature->id)->value('is_enabled'))->toBeFalse();

    actingAs($this->superAdmin)
        ->post(route('avana.klien.feature.toggle', $this->tenant), [
            'feature_id' => $feature->id,
        ])
        ->assertSessionHas('success');

    expect((bool) $this->tenant->features()->where('feature_id', $feature->id)->value('is_enabled'))->toBeTrue();
});

it('forbids an admin_tenant_hr from toggling a tenant feature', function (): void {
    $feature = Feature::firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.klien.feature.toggle', $this->tenant), [
            'feature_id' => $feature->id,
        ])
        ->assertForbidden();
});

it('soft deletes a tenant', function (): void {
    $tenant = Tenant::create([
        'name' => 'PT Sementara',
        'slug' => 'pt-sementara',
        'status' => 'inactive',
    ]);

    actingAs($this->superAdmin)
        ->delete(route('avana.klien.destroy', $tenant))
        ->assertSessionHas('success');

    expect(Tenant::find($tenant->id))->toBeNull();
    expect(Tenant::withTrashed()->find($tenant->id))->not->toBeNull();
});
