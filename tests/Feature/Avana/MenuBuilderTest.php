<?php

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->tenant = Tenant::where('code', '!=', '')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
});

it('seeds the tenant menu from the AvanaNav defaults', function (): void {
    expect(MenuItem::forTenant($this->tenant->id)->where('key', 'crm')->exists())->toBeTrue();
    expect(MenuItem::forTenant($this->tenant->id)->where('key', 'menu-builder')->exists())->toBeTrue();
});

it('adds a custom menu item that appears in the nav', function (): void {
    actingAs($this->admin)
        ->post(route('avana.menu-builder.store'), [
            'label' => 'Portal Vendor',
            'href' => '/avana/crm',
            'icon' => 'briefcase',
            'section' => 'LAYANAN',
            'modules' => ['crm'],
        ])
        ->assertSessionHas('success');

    $item = MenuItem::forTenant($this->tenant->id)->where('label', 'Portal Vendor')->firstOrFail();
    expect($item->key)->toBe('portal-vendor');
    expect($item->is_system)->toBeFalse();

    $nav = collect(AvanaNav::forUser($this->admin->fresh()))->flatMap(fn ($g) => $g['items'])->pluck('label');
    expect($nav)->toContain('Portal Vendor');
});

it('hides an item via toggle so it drops out of the nav', function (): void {
    $crm = MenuItem::forTenant($this->tenant->id)->where('key', 'crm')->firstOrFail();

    actingAs($this->admin)->post(route('avana.menu-builder.toggle', $crm))->assertSessionHas('success');

    expect($crm->fresh()->is_active)->toBeFalse();

    $ids = collect(AvanaNav::forUser($this->admin->fresh()))
        ->flatMap(fn ($g) => $g['items'])
        ->flatMap(fn ($i) => $i['children'] ?? [$i])
        ->pluck('id');
    expect($ids)->not->toContain('crm');
});

it('reorders siblings with move', function (): void {
    $a = MenuItem::create(['tenant_id' => $this->tenant->id, 'key' => 'aaa', 'label' => 'AAA', 'section' => 'TEST', 'sort_order' => 500]);
    $b = MenuItem::create(['tenant_id' => $this->tenant->id, 'key' => 'bbb', 'label' => 'BBB', 'section' => 'TEST', 'sort_order' => 501]);

    actingAs($this->admin)->post(route('avana.menu-builder.move', $b), ['direction' => 'up'])->assertSessionHas('success');

    expect($a->fresh()->sort_order)->toBe(501);
    expect($b->fresh()->sort_order)->toBe(500);
});

it('refuses to delete a system item but deletes a custom one', function (): void {
    $system = MenuItem::forTenant($this->tenant->id)->where('key', 'crm')->firstOrFail();
    actingAs($this->admin)->delete(route('avana.menu-builder.destroy', $system))->assertStatus(422);
    expect($system->fresh())->not->toBeNull();

    $custom = MenuItem::create(['tenant_id' => $this->tenant->id, 'key' => 'ccc', 'label' => 'CCC', 'is_system' => false]);
    actingAs($this->admin)->delete(route('avana.menu-builder.destroy', $custom))->assertSessionHas('success');
    expect(MenuItem::find($custom->id))->toBeNull();
});

it('blocks page access when its menu is hidden', function (): void {
    // Karyawan reachable while active.
    actingAs($this->admin)->get('/avana/employees')->assertOk();

    MenuItem::forTenant($this->tenant->id)->where('key', 'karyawan')->update(['is_active' => false]);

    // Hidden → blocked at the route, even though EmployeeController has a policy.
    actingAs($this->admin)->get('/avana/employees')->assertForbidden();

    MenuItem::forTenant($this->tenant->id)->where('key', 'karyawan')->update(['is_active' => true]);
    actingAs($this->admin)->get('/avana/employees')->assertOk();
});

it('lets a super admin manage another tenant menu and seeds it on demand', function (): void {
    $superadmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $other = Tenant::create(['name' => 'PT Lain', 'company_name' => 'PT Lain', 'slug' => 'lain', 'status' => 'active']);

    expect(MenuItem::forTenant($other->id)->count())->toBe(0);

    actingAs($superadmin)->get(route('avana.menu-builder', ['tenant' => $other->id]))->assertOk();

    // Opening the other tenant seeds its default menu.
    expect(MenuItem::forTenant($other->id)->where('key', 'karyawan')->exists())->toBeTrue();

    // Super admin can add a menu for that tenant.
    actingAs($superadmin)
        ->post(route('avana.menu-builder.store'), [
            'label' => 'Menu Tenant Lain',
            'href' => '/avana/crm',
            'tenant_id' => $other->id,
            'modules' => ['crm'],
        ])
        ->assertSessionHas('success');

    expect(MenuItem::forTenant($other->id)->where('label', 'Menu Tenant Lain')->exists())->toBeTrue();
});

it('forbids a manager from the menu builder', function (): void {
    $manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $manager->roles()->sync([Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail()->id]);

    actingAs($manager)->get(route('avana.menu-builder'))->assertForbidden();
});
