<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\AvanaNav;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the `ai_topup` action permissions, grant the full set to every
 * tenant admin (`admin_tenant_hr`) role, and re-seed the DB-driven navigation so
 * the new "Paket Token AI" (platform) and "Token AI" (tenant) menus appear for
 * existing tenants. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $module = 'ai_topup';
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

        // Grant the full ai_topup.* set to every tenant admin role.
        $topupIds = Permission::where('module', $module)->pluck('id');

        Role::where('code', 'admin_tenant_hr')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($topupIds),
        );

        // Re-seed the DB nav so the new leaves reach existing tenants + platform.
        Tenant::query()->pluck('id')->each(fn (int $id) => AvanaNav::seedDefaultsFor($id));
        AvanaNav::seedPlatformDefaults();
    }

    public function down(): void
    {
        // Non-destructive: leave granted permissions and menu rows in place.
    }
};
