<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('tenants')
                ->whereNull('tenant_group_id')
                ->orderBy('id')
                ->get(['id', 'name', 'company_name'])
                ->each(function (object $tenant): void {
                    $groupId = DB::table('tenant_groups')->insertGetId([
                        'name' => $tenant->company_name ?: $tenant->name,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('tenants')
                        ->where('id', $tenant->id)
                        ->update([
                            'tenant_group_id' => $groupId,
                            'is_primary' => true,
                        ]);
                });

            DB::table('users')
                ->whereNotNull('tenant_id')
                ->orderBy('id')
                ->get(['id', 'tenant_id'])
                ->each(function (object $user): void {
                    DB::table('tenant_memberships')->insertOrIgnore([
                        'user_id' => $user->id,
                        'tenant_id' => $user->tenant_id,
                        'status' => 'active',
                        'is_default' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        DB::table('tenant_memberships')->delete();
        DB::table('tenants')->update(['tenant_group_id' => null]);
        DB::table('tenant_groups')->delete();
    }
};
