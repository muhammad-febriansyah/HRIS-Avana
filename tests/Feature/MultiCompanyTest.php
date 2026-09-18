<?php

use App\Http\Controllers\Avana\CompanySwitcherController;
use App\Http\Controllers\Avana\MultiCompanyController;
use App\Http\Controllers\Avana\TenantController;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::query()->where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->superAdmin = User::query()->where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenant = Tenant::query()->findOrFail($this->admin->tenant_id);

    Route::middleware('web')->group(function (): void {
        Route::get('spec-multi-company', [MultiCompanyController::class, 'index']);
        Route::post('spec-multi-company', [MultiCompanyController::class, 'store']);
        Route::post('spec-switch-company', [CompanySwitcherController::class, 'store']);
        Route::put('spec-multi-company-addon/{tenant}', [TenantController::class, 'updateMultiCompanyAddon']);
        Route::get('spec-current-tenant', fn (): string => (string) request()->user()->tenant_id);
        Route::get('spec-role-codes', fn () => response()->json(request()->user()->roles()->pluck('code')->all()));
    });
});

it('lets the super admin activate the multi company add-on for a tenant group', function (): void {
    actingAs($this->superAdmin)
        ->put('spec-multi-company-addon/'.$this->tenant->id, [
            'enabled' => true,
            'company_limit' => 3,
            'note' => 'Paket grup perusahaan',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $addon = TenantAddon::query()
        ->where('tenant_group_id', $this->tenant->tenant_group_id)
        ->where('code', TenantAddon::MULTI_COMPANY)
        ->firstOrFail();

    expect($addon->status)->toBe('active')
        ->and($addon->company_limit)->toBe(3)
        ->and($addon->note)->toBe('Paket grup perusahaan');
});

it('creates a company inside the same tenant group and grants the creator access', function (): void {
    TenantAddon::create([
        'tenant_group_id' => $this->tenant->tenant_group_id,
        'code' => TenantAddon::MULTI_COMPANY,
        'company_limit' => 2,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post('spec-multi-company', [
            'name' => 'Cabang Operasional Baru',
            'company_name' => 'Cabang Operasional Baru, PT',
            'slug' => 'cabang-operasional-baru',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $company = Tenant::query()->where('slug', 'cabang-operasional-baru')->firstOrFail();

    expect($company->tenant_group_id)->toBe($this->tenant->tenant_group_id)
        ->and($company->is_primary)->toBeFalse()
        ->and(TenantMembership::query()
            ->where('user_id', $this->admin->id)
            ->where('tenant_id', $company->id)
            ->where('status', 'active')
            ->exists())->toBeTrue()
        ->and(Role::query()
            ->where('tenant_id', $company->id)
            ->where('code', 'admin_tenant_hr')
            ->firstOrFail()
            ->users()
            ->whereKey($this->admin->id)
            ->exists())->toBeTrue()
        ->and(MenuItem::query()
            ->where('tenant_id', $company->id)
            ->where('key', 'perusahaan-saya')
            ->exists())->toBeTrue();
});

it('keeps group navigation distinct from company settings navigation', function (): void {
    $labels = collect(AvanaNav::tenantGroups($this->tenant->id))
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('label');

    expect($labels)
        ->toContain('Pengaturan Perusahaan')
        ->toContain('Grup Perusahaan');
});

it('does not allow a user to switch to a company they do not belong to', function (): void {
    $otherTenant = Tenant::query()->create([
        'name' => 'Tenant Lain',
        'company_name' => 'Tenant Lain, PT',
        'slug' => 'tenant-lain',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post('spec-switch-company', ['tenant_id' => $otherTenant->id])
        ->assertSessionHasErrors('tenant_id');
});

it('switches the active tenant only after an active membership is present', function (): void {
    $company = Tenant::query()->create([
        'name' => 'Perusahaan Kedua',
        'company_name' => 'Perusahaan Kedua, PT',
        'slug' => 'perusahaan-kedua',
        'tenant_group_id' => $this->tenant->tenant_group_id,
        'is_primary' => false,
        'status' => 'active',
    ]);

    TenantMembership::create([
        'user_id' => $this->admin->id,
        'tenant_id' => $company->id,
        'status' => 'active',
        'is_default' => false,
    ]);

    actingAs($this->admin)
        ->post('spec-switch-company', ['tenant_id' => $company->id])
        ->assertRedirect();

    $this->get('spec-current-tenant')->assertOk()->assertSee((string) $company->id);
});

it('does not expose a role from another tenant in the active tenant context', function (): void {
    $otherTenant = Tenant::query()->create([
        'name' => 'Tenant Role Lain',
        'company_name' => 'Tenant Role Lain, PT',
        'slug' => 'tenant-role-lain',
        'status' => 'active',
    ]);
    $otherRole = Role::query()->create([
        'tenant_id' => $otherTenant->id,
        'code' => 'other_tenant_role',
        'name' => 'Role Tenant Lain',
        'is_system' => false,
    ]);

    $this->admin->roles()->syncWithoutDetaching([$otherRole->id]);

    actingAs($this->admin)
        ->get('spec-role-codes')
        ->assertOk()
        ->assertJsonMissing(['other_tenant_role']);
});

it('blocks company creation when the add-on is inactive', function (): void {
    actingAs($this->admin)
        ->post('spec-multi-company', [
            'name' => 'Tidak Boleh Dibuat',
            'slug' => 'tidak-boleh-dibuat',
        ])
        ->assertForbidden();

    expect(Tenant::query()->where('slug', 'tidak-boleh-dibuat')->exists())->toBeFalse();
});
