<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a null tenant_id so the platform (super-admin) menu — managed via the
     * Menu Builder like any tenant menu — can live in the same table.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->change();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable(false)->change();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }
};
