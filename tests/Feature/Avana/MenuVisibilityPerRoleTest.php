<?php

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
});

/** Menu labels the given user actually sees in the sidebar. */
function navLabels(User $user): array
{
    $labels = [];

    foreach (AvanaNav::forUser($user, false) as $group) {
        foreach ($group['items'] as $item) {
            foreach ($item['children'] ?? [$item] as $leaf) {
                $labels[] = $leaf['label'];
            }
        }
    }

    return $labels;
}

it('hides a self-service menu from one role only', function (): void {
    expect(navLabels($this->employee))->toContain('Slip Gaji');

    actingAs($this->admin)
        ->post(route('avana.hak-akses.menu.visibility'), [
            'menu_key' => 'saya-slip',
            'role_id' => $this->employeeRole->id,
            'visible' => false,
        ])
        ->assertSessionHas('success');

    expect(navLabels($this->employee))->not->toContain('Slip Gaji');

    // The admin keeps their own sidebar untouched.
    expect(navLabels($this->admin->fresh()))->toContain('Payroll');
});

it('closes the URL of a menu hidden from the role', function (): void {
    actingAs($this->employee)->get(route('avana.saya.slip-gaji'))->assertOk();

    actingAs($this->admin)->post(route('avana.hak-akses.menu.visibility'), [
        'menu_key' => 'saya-slip',
        'role_id' => $this->employeeRole->id,
        'visible' => false,
    ]);

    actingAs($this->employee)->get(route('avana.saya.slip-gaji'))->assertForbidden();
});

it('shows the menu again when visibility is restored', function (): void {
    actingAs($this->admin)->post(route('avana.hak-akses.menu.visibility'), [
        'menu_key' => 'saya-slip',
        'role_id' => $this->employeeRole->id,
        'visible' => false,
    ]);

    actingAs($this->admin)->post(route('avana.hak-akses.menu.visibility'), [
        'menu_key' => 'saya-slip',
        'role_id' => $this->employeeRole->id,
        'visible' => true,
    ]);

    expect(navLabels($this->employee))->toContain('Slip Gaji');
    actingAs($this->employee)->get(route('avana.saya.slip-gaji'))->assertOk();
});

it('keeps a menu that only one of the user roles hides', function (): void {
    $second = Role::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'employee_plus',
        'name' => 'Karyawan Plus',
    ]);
    $this->employee->roles()->attach($second->id);

    RoleMenuVisibility::create([
        'tenant_id' => $this->tenant->id,
        'role_id' => $this->employeeRole->id,
        'menu_key' => 'saya-slip',
        'is_visible' => false,
    ]);

    // Hidden by one role, untouched by the other: most-permissive wins.
    expect(navLabels($this->employee->fresh()))->toContain('Slip Gaji');
    actingAs($this->employee)->get(route('avana.saya.slip-gaji'))->assertOk();
});

it('reports the visibility state per role in the matrix payload', function (): void {
    RoleMenuVisibility::create([
        'tenant_id' => $this->tenant->id,
        'role_id' => $this->employeeRole->id,
        'menu_key' => 'saya-slip',
        'is_visible' => false,
    ]);

    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    $rowIdx = collect($props['modules'])->search(fn (array $row): bool => $row['key'] === 'saya-slip');
    $colIdx = collect($props['roles'])->search(fn (array $role): bool => $role['id'] === $this->employeeRole->id);

    expect($props['matrix'][$rowIdx][$colIdx]['visible'])->toBeFalse();

    // Another role on the same menu is unaffected.
    $otherIdx = collect($props['roles'])->search(fn (array $role): bool => $role['code'] === 'manager');

    expect($props['matrix'][$rowIdx][$otherIdx]['visible'])->toBeTrue();
});

it('turns a single menu off for the whole tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.menu.toggle'), [
            'menu_key' => 'saya-slip',
            'active' => false,
        ])
        ->assertSessionHas('success');

    expect((bool) MenuItem::forTenant($this->tenant->id)->where('key', 'saya-slip')->value('is_active'))
        ->toBeFalse();

    expect(navLabels($this->employee))->not->toContain('Slip Gaji');
    actingAs($this->employee)->get(route('avana.saya.slip-gaji'))->assertForbidden();
});

it('never hides anything from a super admin', function (): void {
    RoleMenuVisibility::create([
        'tenant_id' => $this->tenant->id,
        'role_id' => Role::where('code', 'super_admin')->value('id'),
        'menu_key' => 'klien',
        'is_visible' => false,
    ]);

    actingAs($this->superAdmin)->get(route('avana.klien'))->assertOk();
});

it('refuses to change the visibility of the actors own role', function (): void {
    $ownRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'admin_tenant_hr')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.hak-akses.menu.visibility'), [
            'menu_key' => 'payroll',
            'role_id' => $ownRole->id,
            'visible' => false,
        ])
        ->assertForbidden();
});

it('rejects an unknown menu key', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.menu.visibility'), [
            'menu_key' => 'menu-yang-tidak-ada',
            'role_id' => $this->employeeRole->id,
            'visible' => false,
        ])
        ->assertStatus(422);
});

it('forbids a plain employee from changing menu visibility', function (): void {
    actingAs($this->employee)
        ->post(route('avana.hak-akses.menu.visibility'), [
            'menu_key' => 'saya-slip',
            'role_id' => $this->employeeRole->id,
            'visible' => false,
        ])
        ->assertForbidden();
});

it('lists who holds each role in the payload', function (): void {
    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    $employeeRole = collect($props['roles'])->firstWhere('code', 'employee');

    expect($employeeRole['members'])->not->toBeEmpty()
        ->and(collect($employeeRole['members'])->pluck('email'))
        ->toContain('bagus.p@nusantara.co.id');

    // Tenant accounts available for assignment come along too.
    expect(collect($props['assignableUsers'])->pluck('email'))
        ->toContain('bagus.p@nusantara.co.id');
});

it('puts a user into a role and takes them out again', function (): void {
    $manager = Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.users.attach', $manager), ['user_id' => $this->employee->id])
        ->assertSessionHas('success');

    expect($manager->users()->whereKey($this->employee->id)->exists())->toBeTrue();

    actingAs($this->admin)
        ->delete(route('avana.hak-akses.roles.users.detach', ['role' => $manager->id, 'member' => $this->employee->id]))
        ->assertSessionHas('success');

    expect($manager->fresh()->users()->whereKey($this->employee->id)->exists())->toBeFalse();
});

it('refuses to leave a user with no role at all', function (): void {
    $only = $this->employee->roles()->first();

    actingAs($this->admin)
        ->delete(route('avana.hak-akses.roles.users.detach', ['role' => $only->id, 'member' => $this->employee->id]))
        ->assertSessionHas('error');

    expect($this->employee->fresh()->roles()->count())->toBe(1);
});

it('refuses to restaff the actors own role', function (): void {
    $ownRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'admin_tenant_hr')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.users.attach', $ownRole), ['user_id' => $this->employee->id])
        ->assertForbidden();
});

it('copies permissions and hidden menus into a new role', function (): void {
    $manager = Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail();

    RoleMenuVisibility::create([
        'tenant_id' => $this->tenant->id,
        'role_id' => $manager->id,
        'menu_key' => 'saya-slip',
        'is_visible' => false,
    ]);

    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.store'), [
            'name' => 'Supervisor Cabang',
            'copy_from_role_id' => $manager->id,
        ])
        ->assertSessionHas('success');

    $created = Role::where('tenant_id', $this->tenant->id)->where('name', 'Supervisor Cabang')->firstOrFail();

    expect($created->permissions()->pluck('code')->sort()->values()->all())
        ->toBe($manager->permissions()->pluck('code')->sort()->values()->all());

    expect(RoleMenuVisibility::where('role_id', $created->id)->where('menu_key', 'saya-slip')->value('is_visible'))
        ->toBeFalse();
});

it('creates an empty role when no source is given', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.roles.store'), ['name' => 'Peran Kosong'])
        ->assertSessionHas('success');

    $created = Role::where('tenant_id', $this->tenant->id)->where('name', 'Peran Kosong')->firstOrFail();

    expect($created->permissions()->count())->toBe(0);
});

it('reports an admin menu as not visible until the role holds a permission', function (): void {
    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    $rowIdx = collect($props['modules'])->search(fn (array $row): bool => $row['key'] === 'karyawan');
    $colIdx = collect($props['roles'])->search(fn (array $role): bool => $role['id'] === $this->employeeRole->id);
    $cell = $props['matrix'][$rowIdx][$colIdx];

    // Karyawan holds no `employee` permission, so Data Karyawan is not in their
    // sidebar — the panel must say so instead of claiming "Tampil".
    expect(navLabels($this->employee))->not->toContain('Data Karyawan')
        ->and($cell['visible'])->toBeFalse()
        ->and($cell['granted'])->toBeFalse()
        ->and($cell['hidden'])->toBeFalse();
});

it('grants the view permission when an admin menu is switched on for a role', function (): void {
    actingAs($this->admin)
        ->post(route('avana.hak-akses.menu.visibility'), [
            'menu_key' => 'karyawan',
            'role_id' => $this->employeeRole->id,
            'visible' => true,
        ])
        ->assertSessionHas('success');

    expect($this->employeeRole->fresh()->permissions()->where('code', 'employee.view')->exists())
        ->toBeTrue();

    // And the menu really is in their sidebar now.
    expect(navLabels($this->employee->fresh()))->toContain('Data Karyawan');
});

it('reports a self-service menu as visible without any permission', function (): void {
    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    $rowIdx = collect($props['modules'])->search(fn (array $row): bool => $row['key'] === 'saya-slip');
    $colIdx = collect($props['roles'])->search(fn (array $role): bool => $role['id'] === $this->employeeRole->id);

    expect($props['matrix'][$rowIdx][$colIdx])
        ->toMatchArray(['visible' => true, 'granted' => true, 'hidden' => false]);
});

it('reports a menu switched off for the whole tenant as not visible for any role', function (): void {
    actingAs($this->admin)->post(route('avana.hak-akses.menu.toggle'), [
        'menu_key' => 'saya-slip',
        'active' => false,
    ]);

    $props = actingAs($this->admin)
        ->get(route('avana.hak-akses'))
        ->assertOk()
        ->viewData('page')['props'];

    $rowIdx = collect($props['modules'])->search(fn (array $row): bool => $row['key'] === 'saya-slip');
    $colIdx = collect($props['roles'])->search(fn (array $role): bool => $role['id'] === $this->employeeRole->id);

    expect($props['matrix'][$rowIdx][$colIdx]['visible'])->toBeFalse()
        // The hide switch was never touched, so the fix is the company tab.
        ->and($props['matrix'][$rowIdx][$colIdx]['hidden'])->toBeFalse();
});
