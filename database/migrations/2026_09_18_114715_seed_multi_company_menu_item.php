<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tenants')->orderBy('id')->get(['id'])->each(function (object $tenant): void {
            if (DB::table('menu_items')->where('tenant_id', $tenant->id)->where('key', 'perusahaan-saya')->exists()) {
                return;
            }

            DB::table('menu_items')->insert([
                'tenant_id' => $tenant->id,
                'parent_id' => null,
                'key' => 'perusahaan-saya',
                'section' => 'SISTEM',
                'label' => 'Perusahaan Saya',
                'icon' => 'building-2',
                'href' => '/avana/perusahaan-saya',
                'feature' => 'organization',
                'modules' => json_encode(['organization'], JSON_THROW_ON_ERROR),
                'admin_only' => true,
                'super_admin_only' => false,
                'is_active' => true,
                'is_system' => true,
                'sort_order' => (int) DB::table('menu_items')->where('tenant_id', $tenant->id)->max('sort_order') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')->where('key', 'perusahaan-saya')->delete();
    }
};
