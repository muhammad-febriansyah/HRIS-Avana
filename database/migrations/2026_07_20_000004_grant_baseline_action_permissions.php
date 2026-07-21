<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Fail-open baseline grant for existing databases: for every module a role
 * already holds, grant its full action set so turning on action-level
 * enforcement never strips access a role had before. Additive and idempotent
 * (syncWithoutDetaching never detaches). Fresh installs get the same grant via
 * AvanaDemoSeeder; on an empty roles table this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
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
        // Non-destructive: baseline grants are left in place. Removing them could
        // revoke access an admin has since relied on.
    }
};
