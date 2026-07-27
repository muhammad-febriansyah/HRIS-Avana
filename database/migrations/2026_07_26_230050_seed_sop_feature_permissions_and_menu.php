<?php

use App\Models\Feature;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Register the SOP module for existing installations: the `sop` feature, the
 * `sop.*` action permissions, the tenant-admin grant, the per-tenant feature
 * toggle, and the "SOP" sidebar leaf under Karyawan. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $module = 'sop';

        $feature = Feature::updateOrCreate(
            ['code' => $module],
            ['name' => 'SOP & Prosedur', 'module_group' => 'core', 'permission_modules' => [$module]],
        );

        $existing = DB::table('permissions')->pluck('code')->flip();
        $rows = [];

        foreach (PermissionCatalog::actionKeys() as $action) {
            $code = $module.'.'.$action;

            if ($existing->has($code)) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'module' => $module,
                'action' => $action,
                'name' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('permissions')->insert($rows);
        }

        $permissionIds = Permission::where('module', $module)->pluck('id');

        Role::where('code', 'admin_tenant_hr')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($permissionIds),
        );

        Tenant::query()->get()->each(function (Tenant $tenant) use ($feature): void {
            $tenant->features()->firstOrCreate(
                ['feature_id' => $feature->id],
                ['is_enabled' => true],
            );

            AvanaNav::seedDefaultsFor($tenant->id);
        });
    }

    public function down(): void
    {
        // Non-destructive: leave granted permissions, features and menu rows.
    }
};
