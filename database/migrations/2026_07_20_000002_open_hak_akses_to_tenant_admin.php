<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-open the Hak Akses screen to tenant admins, scoped to their own tenant.
 *
 * The 2026_07_02 migration made Hak Akses a super-admin-only (platform) concern.
 * The product now wants each tenant admin to configure the menu/action rights of
 * the roles inside their tenant (HR, Manager, Finance, Karyawan, ...), so flip
 * the already-seeded TENANT rows to admin-only. The platform (null-tenant) row
 * and Menu Builder stay super-admin-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')
            ->where('key', 'hak-akses')
            ->whereNotNull('tenant_id')
            ->update(['super_admin_only' => false, 'admin_only' => true]);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->where('key', 'hak-akses')
            ->whereNotNull('tenant_id')
            ->update(['super_admin_only' => true]);
    }
};
