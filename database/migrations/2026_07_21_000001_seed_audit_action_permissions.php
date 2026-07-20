<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the `audit.{action}` permission codes. The audit module was added to
 * {@see PermissionCatalog} after the original action-permission backfill ran, so
 * existing databases lack these codes and the Hak Akses matrix cannot toggle the
 * Audit Trail menu without them. Idempotent: only inserts codes that are absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('permissions')->where('module', 'audit')->pluck('code')->flip();

        $rows = [];

        foreach (PermissionCatalog::actionKeys() as $action) {
            $code = 'audit.'.$action;

            if ($existing->has($code)) {
                continue;
            }

            $rows[] = [
                'code' => $code,
                'module' => 'audit',
                'action' => $action,
                'name' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('permissions')->insert($rows);
        }
    }

    public function down(): void
    {
        // Keep audit.view (seeded independently, may be referenced by a role);
        // only remove the codes this migration is responsible for that no role uses.
        $referenced = DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->pluck('permissions.code')
            ->flip();

        $removable = collect(PermissionCatalog::actionKeys())
            ->map(fn (string $action): string => 'audit.'.$action)
            ->reject(fn (string $code): bool => $code === 'audit.view' || $referenced->has($code))
            ->all();

        DB::table('permissions')->whereIn('code', $removable)->delete();
    }
};
