<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-role menu visibility. Permissions answer "may this role act on that
 * module"; this answers "does this role see that menu at all" — which is the only
 * way to hide a self-service (ESS) screen, since every employee holds the same
 * `own` module.
 *
 * A missing row means visible, so the table only ever grows with what an admin
 * explicitly hides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('menu_key');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['role_id', 'menu_key']);
            $table->index(['tenant_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_visibility');
    }
};
