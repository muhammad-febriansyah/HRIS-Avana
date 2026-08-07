<?php

use App\Models\MobileMenuItem;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MobileMenu;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
});

it('seeds the shipped tiles the first time a tenant is asked for them', function (): void {
    expect(MobileMenuItem::forTenant($this->tenant->id)->count())->toBe(0);

    $tiles = MobileMenu::forTenant($this->tenant->id);

    expect($tiles)->toHaveCount(count(MobileMenu::defaults()))
        ->and($tiles->first()->key)->toBe('dasbor')
        // Seeded in the order the app lays them out, not by insertion luck.
        ->and($tiles->pluck('key')->all())->toBe(collect(MobileMenu::defaults())->pluck('key')->all());
});

it('renders the tiles on the Hak Akses screen with a column per role', function (): void {
    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['mobileMenu'])->toHaveCount(count(MobileMenu::defaults()))
        ->and($props['mobileMenu'][0])->toHaveKeys(['id', 'key', 'label', 'icon', 'color', 'route', 'isActive', 'visible'])
        // One flag per role column, aligned with `roles`.
        ->and($props['mobileMenu'][0]['visible'])->toHaveCount(count($props['roles']));
});

it('hides a tile from one role only', function (): void {
    $tile = MobileMenu::forTenant($this->tenant->id)->firstWhere('key', 'reimburse');

    actingAs($this->admin)
        ->post(route('avana.hak-akses.mobile-menu.visibility'), [
            'menu_id' => $tile->id,
            'role_id' => $this->employeeRole->id,
            'visible' => false,
        ])
        ->assertSessionHas('success');

    expect(collect(MobileMenu::forUser($this->employee))->pluck('key'))->not->toContain('reimburse');

    // A colleague on another role still has it.
    $manager = User::where('email', 'budi.s@nusantara.co.id')->firstOrFail();
    expect(collect(MobileMenu::forUser($manager))->pluck('key'))->toContain('reimburse');
});

it('keeps a tile while any one of a multi-role account still shows it', function (): void {
    $tile = MobileMenu::forTenant($this->tenant->id)->firstWhere('key', 'cuti');
    $second = Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail();

    $this->employee->roles()->syncWithoutDetaching([$second->id]);

    RoleMenuVisibility::create([
        'tenant_id' => $this->tenant->id,
        'role_id' => $this->employeeRole->id,
        'menu_key' => $tile->visibilityKey(),
        'is_visible' => false,
    ]);

    expect(collect(MobileMenu::forUser($this->employee->fresh()))->pluck('key'))->toContain('cuti');
});

it('drops a tile switched off for the whole company', function (): void {
    $tile = MobileMenu::forTenant($this->tenant->id)->firstWhere('key', 'perasaan');

    actingAs($this->admin)
        ->put(route('avana.hak-akses.mobile-menu.update'), [
            'menu_id' => $tile->id,
            'active' => false,
        ])
        ->assertSessionHas('success');

    expect(collect(MobileMenu::forUser($this->employee))->pluck('key'))->not->toContain('perasaan');
});

it('renames a tile without touching its key or route', function (): void {
    $tile = MobileMenu::forTenant($this->tenant->id)->firstWhere('key', 'uang_muka');

    actingAs($this->admin)
        ->put(route('avana.hak-akses.mobile-menu.update'), [
            'menu_id' => $tile->id,
            'label' => 'Kasbon',
        ])
        ->assertSessionHas('success');

    expect($tile->fresh())
        ->label->toBe('Kasbon')
        ->key->toBe('uang_muka')
        ->route->toBe('/kasbon');
});

it('reorders the carousel', function (): void {
    $tiles = MobileMenu::forTenant($this->tenant->id);
    $reversed = $tiles->pluck('id')->reverse()->values();

    actingAs($this->admin)
        ->put(route('avana.hak-akses.mobile-menu.order'), ['order' => $reversed->all()])
        ->assertSessionHas('success');

    expect(MobileMenu::forTenant($this->tenant->id)->pluck('id')->all())->toBe($reversed->all());
});

it('serves the resolved menu to the mobile app', function (): void {
    $tile = MobileMenu::forTenant($this->tenant->id)->firstWhere('key', 'settlement');

    RoleMenuVisibility::create([
        'tenant_id' => $this->tenant->id,
        'role_id' => $this->employeeRole->id,
        'menu_key' => $tile->visibilityKey(),
        'is_visible' => false,
    ]);

    $menu = $this->actingAs($this->employee, 'api')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->json('data.menu');

    expect(collect($menu)->pluck('key'))->not->toContain('settlement')
        ->and($menu[0])->toHaveKeys(['key', 'label', 'icon', 'color', 'route']);
});

it('refuses to touch another tenant tiles', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-mobile', 'status' => 'active']);
    MobileMenu::seedDefaultsFor($other->id);
    $stranger = MobileMenuItem::forTenant($other->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.hak-akses.mobile-menu.update'), [
            'menu_id' => $stranger->id,
            'active' => false,
        ])
        ->assertStatus(404);

    expect($stranger->fresh()->is_active)->toBeTrue();
});

it('forbids an employee from rearranging the mobile menu', function (): void {
    $tile = MobileMenu::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->employee)
        ->put(route('avana.hak-akses.mobile-menu.update'), [
            'menu_id' => $tile->id,
            'active' => false,
        ])
        ->assertForbidden();
});

it('records the Menu Cepat picks made while creating a role', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.store'), [
            'name' => 'Supervisor Gudang',
            'can_access_mobile' => true,
            'menus' => [['key' => 'karyawan', 'actions' => ['view']]],
            'mobile_menus' => ['cuti', 'slip_gaji'],
        ])
        ->assertRedirect();

    $role = Role::where('tenant_id', $this->tenant->id)->where('name', 'Supervisor Gudang')->firstOrFail();
    $member = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $member->roles()->sync([$role->id]);

    expect(collect(MobileMenu::forUser($member->fresh()))->pluck('key')->all())
        ->toBe(['cuti', 'slip_gaji']);
});

it('renders the bottom tabs on the Hak Akses screen', function (): void {
    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['mobileTabs'])->toHaveCount(count(MobileMenu::tabDefaults()))
        ->and(array_column($props['mobileTabs'], 'key'))->toBe(['beranda', 'sosmed', 'absensi', 'pengumuman', 'profil'])
        // Beranda and Profil carry the flag that freezes their switch.
        ->and(array_column($props['mobileTabs'], 'locked'))->toBe([true, false, false, false, true]);
});

it('refuses to switch a locked tab off', function (): void {
    $beranda = MobileMenu::tabsForTenant($this->tenant->id)->firstWhere('key', 'beranda');

    actingAs($this->admin)
        ->put(route('avana.hak-akses.mobile-menu.update'), ['menu_id' => $beranda->id, 'active' => false])
        ->assertStatus(422);

    expect($beranda->fresh()->is_active)->toBeTrue();
});

it('refuses to hide a locked tab from a role', function (): void {
    $profil = MobileMenu::tabsForTenant($this->tenant->id)->firstWhere('key', 'profil');

    actingAs($this->admin)
        ->post(route('avana.hak-akses.mobile-menu.visibility'), [
            'menu_id' => $profil->id,
            'role_id' => $this->employeeRole->id,
            'visible' => false,
        ])
        ->assertStatus(422);

    expect(array_column(MobileMenu::tabsForUser($this->employee->fresh()), 'key'))->toContain('profil');
});

it('hides a bottom tab from one role only', function (): void {
    $sosmed = MobileMenu::tabsForTenant($this->tenant->id)->firstWhere('key', 'sosmed');

    actingAs($this->admin)
        ->post(route('avana.hak-akses.mobile-menu.visibility'), [
            'menu_id' => $sosmed->id,
            'role_id' => $this->employeeRole->id,
            'visible' => false,
        ])
        ->assertRedirect();

    expect(RoleMenuVisibility::where('role_id', $this->employeeRole->id)
        ->where('menu_key', $sosmed->visibilityKey())
        ->where('is_visible', false)
        ->exists())->toBeTrue();
});

it('gives every shipped phone icon a web equivalent', function (): void {
    // The Hak Akses cards draw these with Lucide, so an icon missing from the
    // map renders as an empty square — which is how Token AI and AI Recorder
    // ended up blank.
    $map = file_get_contents(resource_path('js/pages/avana/hak-akses/components.tsx'));

    $icons = collect(MobileMenu::defaults())
        ->merge(MobileMenu::tabDefaults())
        ->pluck('icon')
        ->unique();

    $missing = $icons
        ->reject(fn (string $icon): bool => str_contains($map, $icon.':'))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
