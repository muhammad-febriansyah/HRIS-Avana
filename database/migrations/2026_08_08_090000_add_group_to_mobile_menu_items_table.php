<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the phone's menu rows into the two places they appear: the Menu Cepat
 * carousel (`quick`) and the bottom navigation bar (`tab`).
 *
 * The bar used to be a hard-coded Dart list, so switching Ruang Kita off for a
 * company left the tab sitting there. Both now answer to the same tenant
 * switch, per-role visibility and feature gate — one table, one set of rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_menu_items', function (Blueprint $table): void {
            $table->string('group', 16)->default('quick')->after('tenant_id');
            $table->index(['tenant_id', 'group', 'sort_order']);
        });

        // Every row that exists today is a Menu Cepat tile.
        DB::table('mobile_menu_items')->update(['group' => 'quick']);
    }

    public function down(): void
    {
        Schema::table('mobile_menu_items', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'group', 'sort_order']);
            $table->dropColumn('group');
        });
    }
};
