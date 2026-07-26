<?php

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
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
            'admin_name' => 'Admin Maju',
            'admin_email' => 'admin@maju-bersama.co.id',
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
            'admin_name' => 'Admin Cahaya',
            'admin_email' => 'admin@cahaya-abadi.co.id',
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
            'admin_name' => 'Admin Duplikat',
            'admin_email' => 'admin@duplikat.co.id',
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

it('renders the tenant detail page for a super admin with the full profile payload', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.klien.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/klien/show', false)
            ->where('tenant.id', $this->tenant->id)
            ->has('tenant.users_count')
            ->has('tenant.employees_count')
            ->has('tenant.branches_count')
            ->has('tenant.max_employees')
            ->has('subscription.total')
            ->has('billing.paid_total')
            ->has('billing.outstanding')
            ->has('billing.recent')
            ->has('features')
            ->has('branches')
            ->has('departments')
            ->has('employees.active')
            ->has('employees.employment.permanent')
            ->has('employees.recent'));
});

it('forbids an admin_tenant_hr from viewing the tenant detail page', function (): void {
    actingAs($this->admin)
        ->get(route('avana.klien.show', $this->tenant))
        ->assertForbidden();
});

it('provisions roles, menu and a working admin login with the new tenant', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Sinar Baru',
            'slug' => 'sinar-baru',
            'status' => 'active',
            'admin_name' => 'Sari Admin',
            'admin_email' => 'sari@sinar-baru.co.id',
            'admin_password' => 'rahasia123',
        ])
        ->assertSessionHas('success')
        ->assertSessionHas('credentials');

    $tenant = Tenant::where('slug', 'sinar-baru')->firstOrFail();

    // The four system roles, so the user form has something to assign.
    expect(Role::where('tenant_id', $tenant->id)->pluck('code')->sort()->values()->all())
        ->toBe(['admin_tenant_hr', 'employee', 'finance', 'manager']);

    // The sidebar the tenant admin will actually see.
    expect(MenuItem::where('tenant_id', $tenant->id)->count())->toBeGreaterThan(0);

    $admin = User::where('email', 'sari@sinar-baru.co.id')->firstOrFail();

    expect((int) $admin->tenant_id)->toBe($tenant->id);
    expect($admin->roles->pluck('code')->all())->toBe(['admin_tenant_hr']);
    expect(Hash::check('rahasia123', $admin->password))->toBeTrue();

    // And that account can really get in and load its own dashboard.
    actingAs($admin)->get(route('avana.employees.index'))->assertOk();
});

it('generates a password when none is given and returns it once', function (): void {
    $response = actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Tanpa Password',
            'slug' => 'tanpa-password',
            'admin_name' => 'Admin Otomatis',
            'admin_email' => 'admin@tanpa-password.co.id',
        ]);

    $credentials = session('credentials');

    expect($credentials['email'])->toBe('admin@tanpa-password.co.id');
    expect(strlen((string) $credentials['password']))->toBeGreaterThanOrEqual(8);

    $admin = User::where('email', 'admin@tanpa-password.co.id')->firstOrFail();

    expect(Hash::check($credentials['password'], $admin->password))->toBeTrue();
    $response->assertRedirect(route('avana.klien.show', Tenant::where('slug', 'tanpa-password')->firstOrFail()));
});

it('requires the admin account fields when creating a tenant', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), ['name' => 'PT Tanpa Admin'])
        ->assertSessionHasErrors(['admin_name', 'admin_email']);

    expect(Tenant::where('name', 'PT Tanpa Admin')->exists())->toBeFalse();
});

it('rejects an admin email that already belongs to another account', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.store'), [
            'name' => 'PT Email Kembar',
            'admin_name' => 'Admin Kembar',
            'admin_email' => $this->admin->email,
        ])
        ->assertSessionHasErrors('admin_email');
});

it('lists the tenant admins on the klien detail page', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.klien.show', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/klien/show', false)
            ->has('admins')
            ->where('admins.0.email', 'rina.a@nusantara.co.id')
            ->etc());
});

it('adds another admin to an existing tenant', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.admin.store', $this->tenant), [
            'admin_name' => 'Admin Kedua',
            'admin_email' => 'kedua@nusantara.co.id',
        ])
        ->assertSessionHas('credentials');

    $admin = User::where('email', 'kedua@nusantara.co.id')->firstOrFail();

    expect((int) $admin->tenant_id)->toBe($this->tenant->id);
    expect($admin->roles->pluck('code')->all())->toBe(['admin_tenant_hr']);
});

it('resets a tenant admin password and hands it back once', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.klien.admin.password', [$this->tenant, $this->admin]), [
            'admin_password' => 'passwordbaru1',
        ])
        ->assertSessionHas('success');

    expect(Hash::check('passwordbaru1', $this->admin->fresh()->password))->toBeTrue();
});

it('refuses to reset a password for a user outside the tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active']);

    actingAs($this->superAdmin)
        ->post(route('avana.klien.admin.password', [$other, $this->admin]), [])
        ->assertNotFound();
});

it('forbids a tenant admin from adding admins to a tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.klien.admin.store', $this->tenant), [
            'admin_name' => 'Admin Selundupan',
            'admin_email' => 'selundupan@nusantara.co.id',
        ])
        ->assertForbidden();

    expect(User::where('email', 'selundupan@nusantara.co.id')->exists())->toBeFalse();
});

it('back-fills features, roles and menu when an admin is added to a bare tenant', function (): void {
    // A client created before the provisioner existed: no roles, no menu, no
    // features — adding an admin has to make it usable, not just log-in-able.
    $bare = Tenant::create(['name' => 'PT Warisan', 'slug' => 'pt-warisan', 'status' => 'active']);

    expect(Role::where('tenant_id', $bare->id)->count())->toBe(0);

    actingAs($this->superAdmin)
        ->post(route('avana.klien.admin.store', $bare), [
            'admin_name' => 'Admin Warisan',
            'admin_email' => 'admin@warisan.co.id',
        ])
        ->assertSessionHas('credentials');

    expect(Role::where('tenant_id', $bare->id)->count())->toBe(4);
    expect(MenuItem::where('tenant_id', $bare->id)->count())->toBeGreaterThan(0);
    expect($bare->features()->where('is_enabled', true)->count())->toBe(Feature::count());
});
