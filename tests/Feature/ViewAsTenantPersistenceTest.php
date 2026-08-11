<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->homeTenantId = $this->superAdmin->tenant_id;
    // Prefer a tenant that has an admin of its own: one case signs in as that
    // admin to read their dashboard.
    $this->otherTenant = Tenant::where('id', '!=', $this->homeTenantId)
        ->whereIn('id', User::query()
            ->whereHas('roles', fn ($query) => $query->where('code', 'admin_tenant_hr'))
            ->select('tenant_id'))
        ->first()
        ?? Tenant::where('id', '!=', $this->homeTenantId)->first()
        ?? Tenant::create(['name' => 'Tenant Lain', 'slug' => 'tenant-lain', 'status' => 'active']);
});

it('does not persist the viewed tenant onto the super admin when they save their profile', function (): void {
    $this->actingAs($this->superAdmin)
        ->withSession(['view_tenant_id' => $this->otherTenant->id])
        ->patch('/settings/profile', [
            'name' => $this->superAdmin->name,
            'email' => $this->superAdmin->email,
        ]);

    expect($this->superAdmin->fresh()->tenant_id)->toBe($this->homeTenantId);
});

it('keeps the profile edit itself working while viewing another tenant', function (): void {
    $this->actingAs($this->superAdmin)
        ->withSession(['view_tenant_id' => $this->otherTenant->id])
        ->patch('/settings/profile', [
            'name' => 'Nama Baru',
            'email' => $this->superAdmin->email,
        ]);

    $fresh = $this->superAdmin->fresh();

    expect($fresh->name)->toBe('Nama Baru')
        ->and($fresh->tenant_id)->toBe($this->homeTenantId);
});

it('still shows the viewed tenant data during the request', function (): void {
    $this->actingAs($this->superAdmin)
        ->withSession(['view_tenant_id' => $this->otherTenant->id])
        ->get('/dashboard')
        ->assertOk();

    // The override is for the request only; nothing is written back.
    expect($this->superAdmin->fresh()->tenant_id)->toBe($this->homeTenantId);
});

it('leaves a platform account out of the tenant orphan-account warning', function (): void {
    // A super admin sitting in a tenant with no employee record of their own
    // used to be reported to that tenant's admin as an account to link.
    expect($this->superAdmin->employee)->toBeNull();

    $tenantAdmin = User::where('tenant_id', $this->superAdmin->tenant_id)
        ->whereHas('roles', fn ($query) => $query->where('code', 'admin_tenant_hr'))
        ->firstOrFail();

    $this->actingAs($tenantAdmin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'orphanAccounts',
            fn ($accounts) => collect($accounts)->pluck('email')->doesntContain('superadmin@avanahr.id'),
        ));
});
