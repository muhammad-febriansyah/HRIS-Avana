<?php

use App\Http\Controllers\Avana\AccessController;
use App\Models\AuditLog;
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
     * Self-contained routes pointing at the controller. Registering them under
     * plain `web` exercises the controller's own guards (scoping, self-lockout)
     * in isolation from the avana route-group middleware.
     */
    Route::middleware('web')->prefix('__access')->group(function (): void {
        Route::get('/', [AccessController::class, 'index']);
        Route::post('/toggle', [AccessController::class, 'togglePermission']);
        Route::post('/roles', [AccessController::class, 'storeRole']);
    });

    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the hak-akses screen with roles, actions and the per-action matrix', function (): void {
    actingAs($this->superAdmin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/hak-akses/index', false)
            ->has('roles.0', fn (Assert $role) => $role
                ->has('id')->has('name')->has('code')->has('desc')
                ->has('users')->has('color')->has('locked'))
            ->has('actions', 6)
            ->has('actions.0', fn (Assert $action) => $action->has('key')->has('label'))
            ->has('modules')
            ->has('modules.0', fn (Assert $module) => $module->has('key')->has('label')->has('actionable'))
            ->has('permHeaders')
            ->has('matrix')
            ->where('isSuperAdmin', true));
});

it('exposes a matrix cell per action for every module/role pairing', function (): void {
    actingAs($this->superAdmin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $roleCount = Role::query()
                ->where('tenant_id', $this->superAdmin->tenant_id)
                ->orWhereNull('tenant_id')
                ->count();

            $page->has('roles', $roleCount)
                ->has('permHeaders', $roleCount)
                ->has('modules', 21)
                ->has('matrix', 21)
                ->has('matrix.0', $roleCount)
                ->has('matrix.1.0', fn (Assert $cell) => $cell
                    ->has('view')->has('create')->has('update')
                    ->has('archive')->has('export')->has('approve'));
        });
});

it('toggles a single action of a menu on and off for a role', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $exportId = Permission::where('code', 'employee.export')->value('id');

    expect($employeeRole->permissions()->whereKey($exportId)->exists())->toBeFalse();

    // First toggle grants employee.export (not the whole module).
    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'export', 'role_id' => $employeeRole->id])
        ->assertSessionHas('success', 'Hak akses diperbarui');

    expect($employeeRole->fresh()->permissions()->whereKey($exportId)->exists())->toBeTrue();
    // A different action of the same module stays untouched.
    $createId = Permission::where('code', 'employee.create')->value('id');
    expect($employeeRole->fresh()->permissions()->whereKey($createId)->exists())->toBeFalse();

    // Second toggle revokes it.
    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'export', 'role_id' => $employeeRole->id])
        ->assertSessionHas('success');

    expect($employeeRole->fresh()->permissions()->whereKey($exportId)->exists())->toBeFalse();
});

it('toggles every module a menu covers for one action', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    // "Cuti & Lembur" covers leave, overtime and wfh.
    $ids = Permission::whereIn('code', ['leave.approve', 'overtime.approve', 'wfh.approve'])->pluck('id');

    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'cuti-lembur', 'action' => 'approve', 'role_id' => $employeeRole->id])
        ->assertSessionHas('success');

    expect($employeeRole->fresh()->permissions()->whereIn('permissions.id', $ids)->count())->toBe($ids->count());
});

it('toggles the audit trail menu permission for a role', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $viewId = Permission::where('code', 'audit.view')->value('id');

    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'audit', 'action' => 'view', 'role_id' => $employeeRole->id])
        ->assertSessionHas('success');

    expect($employeeRole->fresh()->permissions()->whereKey($viewId)->exists())->toBeTrue();
});

it('records an audit log row for a permission change', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'view', 'role_id' => $employeeRole->id]);

    expect(AuditLog::where('auditable_type', $employeeRole->getMorphClass())
        ->where('auditable_id', $employeeRole->id)
        ->where('action', 'permission.updated')
        ->exists())->toBeTrue();
});

it('rejects an unknown action', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'destroy', 'role_id' => $employeeRole->id])
        ->assertSessionHasErrors('action');
});

it('refuses to modify the system super admin role', function (): void {
    $superAdminRole = Role::where('code', 'super_admin')->firstOrFail();

    actingAs($this->superAdmin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'view', 'role_id' => $superAdminRole->id])
        ->assertForbidden();
});

it('creates a tenant role from a name', function (): void {
    actingAs($this->superAdmin)
        ->post('/__access/roles', ['name' => 'Auditor Internal'])
        ->assertSessionHas('success', 'Role dibuat');

    $role = Role::where('tenant_id', $this->superAdmin->tenant_id)->where('code', 'auditor-internal')->firstOrFail();
    expect($role->name)->toBe('Auditor Internal');
    expect($role->is_system)->toBeFalse();
});

it('validates that a role name is required', function (): void {
    actingAs($this->superAdmin)
        ->post('/__access/roles', ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('forbids a plain employee from the access matrix', function (): void {
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail()->id]);

    actingAs($staff)->get('/__access')->assertForbidden();
});

// --- Tenant admin (newly opened, scoped to own tenant) ---------------------

it('lets a tenant admin open the access matrix scoped to their own tenant', function (): void {
    actingAs($this->admin)
        ->get('/__access')
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $page->where('isSuperAdmin', false);

            // Only this tenant's roles — never the global super_admin.
            $codes = collect($page->toArray()['props']['roles'])->pluck('code');
            expect($codes)->not->toContain('super_admin');
            expect($codes->count())->toBe(
                Role::where('tenant_id', $this->admin->tenant_id)->count(),
            );
        });
});

it('locks the tenant admin\'s own role against self-lockout', function (): void {
    $ownRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'admin_tenant_hr')->firstOrFail();

    actingAs($this->admin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'archive', 'role_id' => $ownRole->id])
        ->assertForbidden();
});

it('lets a tenant admin toggle a subordinate role', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $viewId = Permission::where('code', 'asset.view')->value('id');

    actingAs($this->admin)
        ->post('/__access/toggle', ['module_key' => 'layanan', 'action' => 'view', 'role_id' => $employeeRole->id])
        ->assertSessionHas('success');

    expect($employeeRole->fresh()->permissions()->whereKey($viewId)->exists())->toBeTrue();
});

it('stops a tenant admin from touching another tenant\'s role', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'company_name' => 'PT Lain', 'slug' => 'lain', 'status' => 'active']);
    $otherRole = Role::create(['tenant_id' => $other->id, 'code' => 'staff', 'name' => 'Staff', 'is_system' => false]);

    actingAs($this->admin)
        ->post('/__access/toggle', ['module_key' => 'karyawan', 'action' => 'view', 'role_id' => $otherRole->id])
        ->assertNotFound();
});

it('lets a tenant admin reach the real hak-akses route (middleware allows admin)', function (): void {
    actingAs($this->admin)->get(route('avana.hak-akses'))->assertOk();
});
