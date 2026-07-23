<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the action-level permissions for modules added after the original
 * catalogue backfill ran (notably `attrition`), then re-apply the fail-open
 * baseline grant so any role already holding the module gets its full action
 * set. Idempotent — mirrors 2026_07_20_000001 + 000004 for the new module.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('permissions')->pluck('code')->flip();

        $rows = [];
        foreach (PermissionCatalog::MODULES as $module) {
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
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('permissions')->insert($chunk);
        }

        // Fail-open baseline: for every module a role already holds, grant the
        // full action set (so a role with attrition.view also gets .update).
        $codeToId = Permission::pluck('id', 'code');

        Role::with('permissions:id,code,module')->chunk(100, function ($roles) use ($codeToId): void {
            foreach ($roles as $role) {
                $held = $role->permissions->pluck('module')->all();

                $ids = collect(PermissionCatalog::actionCodesForModules($held))
                    ->map(fn (string $code) => $codeToId->get($code))
                    ->filter()
                    ->all();

                if ($ids !== []) {
                    $role->permissions()->syncWithoutDetaching($ids);
                }
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: leave granted permissions in place.
    }
};
