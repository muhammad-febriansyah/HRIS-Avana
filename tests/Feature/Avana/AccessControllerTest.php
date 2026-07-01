<?php

use App\Http\Controllers\Avana\AccessController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();

    /*
     * Self-contained routes pointing at the controller. The production routes
     * (avana.hak-akses, .permission.toggle, .roles.store) are wired by the
     * orchestrator in routes/avana.php; registering them here keeps this suite
     * green in isolation while exercising the real controller actions.
     */
    Route::middleware('web')->prefix('__access')->group(function (): void {
        Route::get('/', [AccessController::class, 'index']);
        Route::post('/toggle', [AccessController::class, 'togglePermission']);
        Route::post('/roles', [AccessController::class, 'storeRole']);
    });

    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the hak-akses screen with roles, modules and the permission matrix', function (): void {
    actingAs($this->admin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/hak-akses/index', false)
            ->has('roles')
            ->has('roles.0', fn (Assert $role) => $role
                ->has('id')
                ->has('name')
                ->has('code')
                ->has('desc')
                ->has('users')
                ->has('color'))
            ->has('modules')
            ->has('permHeaders')
            ->has('matrix'));
});

it('exposes a matrix row per module with one boolean per role', function (): void {
    actingAs($this->admin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $roleCount = Role::query()
                ->where('tenant_id', $this->tenant->id)
                ->orWhereNull('tenant_id')
                ->count();

            $page->has('roles', $roleCount)
                ->has('permHeaders', $roleCount)
                ->has('modules', 20)
                ->has('matrix', 20)
                ->has('matrix.0', $roleCount);
        });
});

it('toggles a module on and off for a role', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $employeePermissionIds = Permission::where('module', 'employee')->pluck('id');

    expect($employeeRole->permissions()->whereIn('permissions.id', $employeePermissionIds)->exists())->toBeFalse();

    // First toggle attaches the whole module.
    actingAs($this->admin)
        ->post('/__access/toggle', [
            'module_key' => 'karyawan',
            'role_id' => $employeeRole->id,
        ])
        ->assertSessionHas('success', 'Hak akses diperbarui');

    expect($employeeRole->fresh()->permissions()->whereIn('permissions.id', $employeePermissionIds)->count())
        ->toBe($employeePermissionIds->count());

    // Second toggle detaches the whole module.
    actingAs($this->admin)
        ->post('/__access/toggle', [
            'module_key' => 'karyawan',
            'role_id' => $employeeRole->id,
        ])
        ->assertSessionHas('success');

    expect($employeeRole->fresh()->permissions()->whereIn('permissions.id', $employeePermissionIds)->exists())->toBeFalse();
});

it('refuses to modify the system super admin role', function (): void {
    $superAdmin = Role::where('code', 'super_admin')->firstOrFail();

    actingAs($this->admin)
        ->post('/__access/toggle', [
            'module_key' => 'karyawan',
            'role_id' => $superAdmin->id,
        ])
        ->assertForbidden();
});

it('creates a tenant role from a name', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', ['name' => 'Auditor Internal'])
        ->assertSessionHas('success', 'Role dibuat');

    $role = Role::where('tenant_id', $this->tenant->id)->where('code', 'auditor-internal')->firstOrFail();

    expect($role->name)->toBe('Auditor Internal');
    expect($role->tenant_id)->toBe($this->tenant->id);
    expect($role->is_system)->toBeFalse();
});

it('validates that a role name is required', function (): void {
    actingAs($this->admin)
        ->post('/__access/roles', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('forbids non-admin users from viewing the access matrix', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get('/__access')
        ->assertForbidden();
});
