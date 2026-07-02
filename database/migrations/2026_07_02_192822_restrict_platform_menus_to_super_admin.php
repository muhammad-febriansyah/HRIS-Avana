<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Menu Builder and Hak Akses are platform (super-admin) concerns. Flip the
     * already-seeded tenant menu rows so they are hidden and route-blocked for
     * tenant admins going forward.
     */
    public function up(): void
    {
        DB::table('menu_items')
            ->whereIn('key', ['menu-builder', 'hak-akses'])
            ->update(['super_admin_only' => true]);
    }

    public function down(): void
    {
        DB::table('menu_items')
            ->whereIn('key', ['menu-builder', 'hak-akses'])
            ->update(['super_admin_only' => false]);
    }
};
