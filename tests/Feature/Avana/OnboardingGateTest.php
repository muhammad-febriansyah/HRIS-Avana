<?php

use App\Models\Company;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

/**
 * EnsureOnboardingComplete is a GLOBAL web middleware — every test here also
 * guards against it accidentally locking out tenants that already have a
 * package and a company profile (i.e. every tenant that existed before this
 * gate shipped).
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
});

/**
 * A tenant in the exact state a self-serve approval leaves it in: trial,
 * no package, no company profile, one admin login.
 */
function makeOnboardingTenant(): array
{
    $tenant = Tenant::create([
        'name' => 'PT Onboarding Baru',
        'company_name' => 'PT Onboarding Baru',
        'slug' => 'pt-onboarding-baru-'.uniqid(),
        'package_id' => null,
        'requires_onboarding' => true,
        'status' => 'trial',
        'max_users' => 0,
        'max_employees' => 0,
        'max_branches' => 0,
        'billing_status' => 'active',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(14)->toDateString(),
    ]);

    $provisioner = app(TenantProvisioner::class);
    $provisioner->provision($tenant);
    $admin = $provisioner->createAdmin($tenant, 'Admin Onboarding', 'admin.onboarding@example.com', 'password')['user'];

    return [$tenant, $admin];
}

it('redirects a tenant with no package and no company profile to the onboarding checklist', function (): void {
    [, $admin] = makeOnboardingTenant();

    actingAs($admin)->get(route('dashboard'))->assertRedirect(route('avana.mulai'));
});

it('lets an incomplete tenant reach the checklist, billing, and company profile pages', function (): void {
    [, $admin] = makeOnboardingTenant();

    actingAs($admin)->get(route('avana.mulai'))->assertOk();
    actingAs($admin)->get(route('avana.langganan'))->assertOk();
    actingAs($admin)->get(route('avana.perusahaan'))->assertOk();
});

it('stays locked once only the company profile is done', function (): void {
    [$tenant, $admin] = makeOnboardingTenant();
    Company::create(['tenant_id' => $tenant->id, 'name' => 'PT Onboarding Baru']);

    actingAs($admin)->get(route('dashboard'))->assertRedirect(route('avana.mulai'));
});

it('stays locked once only a package is picked', function (): void {
    [$tenant, $admin] = makeOnboardingTenant();
    $package = Package::query()->first() ?? Package::create([
        'name' => 'Basic', 'code' => 'basic-onboarding-test', 'price' => 1_000_000, 'is_active' => true,
    ]);
    $tenant->update(['package_id' => $package->id]);

    actingAs($admin)->get(route('dashboard'))->assertRedirect(route('avana.mulai'));
});

it('unlocks the whole app once both a package and a company profile exist', function (): void {
    [$tenant, $admin] = makeOnboardingTenant();
    Company::create(['tenant_id' => $tenant->id, 'name' => 'PT Onboarding Baru']);
    $package = Package::query()->first() ?? Package::create([
        'name' => 'Basic', 'code' => 'basic-onboarding-test-2', 'price' => 1_000_000, 'is_active' => true,
    ]);
    $tenant->update(['package_id' => $package->id]);

    actingAs($admin)->get(route('dashboard'))->assertOk();
});

it('never re-locks a tenant that finished onboarding once, even if a package is later removed', function (): void {
    [$tenant, $admin] = makeOnboardingTenant();
    Company::create(['tenant_id' => $tenant->id, 'name' => 'PT Onboarding Baru']);
    $package = Package::query()->first() ?? Package::create([
        'name' => 'Basic', 'code' => 'basic-onboarding-test-3', 'price' => 1_000_000, 'is_active' => true,
    ]);
    $tenant->update(['package_id' => $package->id]);

    // First request past the gate flips the one-way latch.
    actingAs($admin)->get(route('dashboard'))->assertOk();
    expect($tenant->fresh()->requires_onboarding)->toBeFalse();

    // Downgrading to "Tanpa Paket" afterwards must never re-lock them.
    $tenant->update(['package_id' => null]);
    actingAs($admin)->get(route('dashboard'))->assertOk();
});

it('never locks out an already-onboarded existing tenant', function (): void {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($admin)->get(route('dashboard'))->assertOk();
});

it('never locks out a super admin', function (): void {
    $superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    actingAs($superAdmin)->get(route('dashboard'))->assertOk();
});
