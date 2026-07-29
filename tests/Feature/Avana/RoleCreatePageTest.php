<?php

use App\Http\Controllers\Avana\AccessController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();

    Route::middleware('web')->prefix('__access')->group(function (): void {
        Route::get('/', [AccessController::class, 'index']);
        Route::get('/peran/baru', [AccessController::class, 'createRole']);
        Route::post('/roles', [AccessController::class, 'storeRole']);
    });

    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    /** The tenant's real menus, which is what the page offers. */
    $this->menus = collect(AvanaNav::menuRows($this->admin->tenant_id));
});

it('renders the create-role page from the tenant own menus', function (): void {
    actingAs($this->admin)
        ->get('/__access/peran/baru')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/hak-akses/role-create', false)
            ->has('modules', $this->menus->count())
            ->has('modules.0', fn (Assert $module) => $module
                ->has('key')->has('label')->has('group')->has('parent')->has('href')
                ->has('actionable')->has('permissionModules')->has('menuActive')->has('lockedActive')
                ->has('menuItemId')->has('feature')->has('featureLabel')
                ->has('hasFeature')->has('featureEnabled')->has('selfService'))
            ->has('actions', 6)
            ->has('templates.0', fn (Assert $template) => $template
                ->has('id')->has('name')->has('canAccessMobile')->has('selection')
                ->has('mobileSelection'))
            // The phone's Menu Cepat is picked here too, not on a second visit.
            ->has('mobileMenu'));
});

it('never offers super admin as a template', function (): void {
    actingAs($this->admin)
        ->get('/__access/peran/baru')
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $names = collect($page->toArray()['props']['templates'])->pluck('name');

            expect($names)->not->toContain('Super Admin');
        });
});

it('creates a role with exactly the menus and actions picked', function (): void {
    $asset = $this->menus->firstWhere('key', 'aset');

    expect($asset)->not->toBeNull();

    actingAs($this->admin)
        ->post('/__access/roles', [
            'name' => 'Petugas Aset',
            'description' => 'Kelola inventaris kantor',
            'menus' => [
                ['key' => 'aset', 'actions' => ['view', 'create']],
            ],
        ])
        ->assertRedirect();

    $role = Role::where('code', 'petugas-aset')->firstOrFail();

    expect($role->description)->toBe('Kelola inventaris kantor')
        ->and($role->permissions->pluck('code')->sort()->values()->all())
        ->toBe(['asset.create', 'asset.view']);

    // The picked menu is shown; everything else is explicitly hidden, so a
    // self-service screen does not leak in through the `own` module.
    expect((bool) RoleMenuVisibility::where('role_id', $role->id)->where('menu_key', 'aset')->value('is_visible'))->toBeTrue()
        ->and(RoleMenuVisibility::where('role_id', $role->id)->where('is_visible', false)->count())
        ->toBe($this->menus->count() - 1);
});

it('grants Lihat to a picked menu left without any action', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', [
            'name' => 'Pengamat Aset',
            'menus' => [['key' => 'aset', 'actions' => []]],
        ])
        ->assertRedirect();

    $role = Role::where('code', 'pengamat-aset')->firstOrFail();

    expect($role->permissions->pluck('code')->all())->toBe(['asset.view']);
});

it('creates a role that sees nothing when no menu is picked', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', ['name' => 'Peran Kosong', 'menus' => []])
        ->assertRedirect();

    $role = Role::where('code', 'peran-kosong')->firstOrFail();

    expect($role->permissions)->toHaveCount(0)
        ->and(RoleMenuVisibility::where('role_id', $role->id)->where('is_visible', false)->count())
        ->toBe($this->menus->count());
});

it('gives a second role of the same name its own code', function (): void {
    actingAs($this->admin)->post('/__access/roles', ['name' => 'Supervisor', 'menus' => []])->assertRedirect();
    actingAs($this->admin)->post('/__access/roles', ['name' => 'Supervisor', 'menus' => []])->assertRedirect();

    expect(Role::where('tenant_id', $this->admin->tenant_id)->where('name', 'Supervisor')->pluck('code')->all())
        ->toBe(['supervisor', 'supervisor-2']);
});

it('rejects an action that is not in the catalog', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', [
            'name' => 'Peran Aneh',
            'menus' => [['key' => 'aset', 'actions' => ['detonate']]],
        ])
        ->assertSessionHasErrors('menus.0.actions.0');

    expect(Role::where('code', 'peran-aneh')->exists())->toBeFalse();
});

it('shows the typed description on the access screen', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', [
            'name' => 'Kepala Gudang',
            'description' => 'Pegang stok & aset gudang',
            'menus' => [['key' => 'aset', 'actions' => ['view']]],
        ])
        ->assertRedirect();

    actingAs($this->admin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $role = collect($page->toArray()['props']['roles'])->firstWhere('name', 'Kepala Gudang');

            expect($role['desc'])->toBe('Pegang stok & aset gudang');
        });
});

it('grants the self-service baseline when a Layanan Saya menu is picked', function (): void {
    // Picking Cuti Saya used to show the menu and then 403 the page: `own` is
    // filtered out of permissionModules because every seeded role already holds
    // it, but a role built here starts with nothing, so it held nothing.
    actingAs($this->admin)
        ->post('/__access/roles', [
            'name' => 'Staf Lapangan',
            'menus' => [['key' => 'saya-cuti', 'actions' => []]],
        ])
        ->assertRedirect();

    $role = Role::where('code', 'staf-lapangan')->firstOrFail();

    expect($role->permissions()->where('module', 'own')->count())
        ->toBe(Permission::where('module', 'own')->count());

    // And the page it was picked for actually opens. Uses a seeded account
    // because ESS screens also need an employee record behind the login.
    $member = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $member->roles()->sync([$role->id]);

    actingAs($member->fresh())->get(route('avana.saya.cuti'))->assertOk();
});
