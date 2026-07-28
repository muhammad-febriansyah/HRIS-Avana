<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the `langganan` action permissions, grant the full set to every
 * tenant admin (`admin_tenant_hr`) role, and re-seed the DB-driven navigation so
 * the new "Langganan" menu appears for existing tenants. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $module = 'langganan';
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

        // Only a tenant admin renews the subscription; HR can be granted it later.
        $ids = Permission::where('module', $module)->pluck('id');

        Role::where('code', 'admin_tenant_hr')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids),
        );

        Tenant::query()->pluck('id')->each(fn (int $id) => AvanaNav::seedDefaultsFor($id));
    }

    public function down(): void
    {
        // Non-destructive: leave granted permissions and menu rows in place.
    }
};
