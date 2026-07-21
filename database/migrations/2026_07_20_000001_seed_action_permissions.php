<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the full action-level permission catalogue ({module}.{action}) so the
 * Hak Akses matrix can grant per-action rights (view/create/update/archive/
 * export/approve) per role. Idempotent: only inserts codes that do not yet
 * exist, leaving any hand-curated permission (payroll.run, team.claim.approve,
 * ...) untouched.
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
    }

    public function down(): void
    {
        // Only remove codes this migration is responsible for; any code that a
        // role still references is kept so existing grants never dangle.
        $referenced = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->pluck('permissions.code')
            ->flip();

        $removable = array_filter(
            PermissionCatalog::codes(),
            fn (string $code): bool => ! $referenced->has($code),
        );

        DB::table('permissions')->whereIn('code', $removable)->delete();
    }
};
