<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Let a plain karyawan use the AI assistant on the web.
 *
 * The mobile API never gated the assistant, so employees could already chat
 * from the app while `/avana/ai` returned 403 for the same person. The tools
 * are already scoped per user — the personal ones read only the caller's own
 * record, and SOPs are filtered by visibility — so `ai.view` exposes nothing
 * an employee cannot already see about themselves.
 *
 * The whole `ai` module is granted, matching what AvanaDemoSeeder's fail-open
 * baseline produces for a freshly seeded tenant — otherwise an existing tenant
 * and a new one would end up with different rights. `ai.archive` only lets a
 * user delete their OWN conversation; AiAssistantController@destroyConversation
 * 404s on anyone else's. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $aiPermissionIds = Permission::where('module', 'ai')->pluck('id');

        if ($aiPermissionIds->isEmpty()) {
            return;
        }

        Role::where('code', 'employee')->get()->each(
            fn (Role $role) => $role->permissions()->syncWithoutDetaching($aiPermissionIds),
        );
    }

    public function down(): void
    {
        $aiPermissionIds = Permission::where('module', 'ai')->pluck('id');

        if ($aiPermissionIds->isEmpty()) {
            return;
        }

        Role::where('code', 'employee')->get()->each(
            fn (Role $role) => $role->permissions()->detach($aiPermissionIds),
        );
    }
};
