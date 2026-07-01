<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->superadmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->ownTenantId = $this->superadmin->tenant_id;
    $this->other = Tenant::create(['name' => 'PT Tenant Lain', 'company_name' => 'PT Tenant Lain', 'slug' => 'lain', 'status' => 'active']);
});

it('super admin sees their own tenant by default', function (): void {
    actingAs($this->superadmin)
        ->get('/avana/organisasi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.tenant.id', $this->ownTenantId));
});

it('switching stores the viewed tenant in the session', function (): void {
    actingAs($this->superadmin)
        ->post(route('avana.view-tenant'), ['tenant_id' => $this->other->id])
        ->assertRedirect()
        ->assertSessionHas('view_tenant_id', $this->other->id);
});

it('clearing removes the viewed tenant from the session', function (): void {
    actingAs($this->superadmin)
        ->withSession(['view_tenant_id' => $this->other->id])
        ->post(route('avana.view-tenant'), ['tenant_id' => ''])
        ->assertRedirect()
        ->assertSessionMissing('view_tenant_id');
});

it('applies the viewed tenant to the data a super admin sees', function (): void {
    actingAs($this->superadmin)
        ->withSession(['view_tenant_id' => $this->other->id])
        ->get('/avana/organisasi')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.tenant.id', $this->other->id));
});

it('forbids a non-super-admin from switching tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.view-tenant'), ['tenant_id' => $this->other->id])
        ->assertForbidden();
});

it('never overrides tenant for a non-super-admin even if the session is tampered', function (): void {
    actingAs($this->admin)
        ->withSession(['view_tenant_id' => $this->other->id])
        ->get('/avana/organisasi')
        ->assertInertia(fn (Assert $page) => $page->where('auth.tenant.id', $this->admin->tenant_id));
});
