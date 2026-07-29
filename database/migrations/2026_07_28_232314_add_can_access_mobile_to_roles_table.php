<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-role switch for the mobile (Flutter) app. Web access is decided by
 * permissions and menu visibility; the phone is a separate question — a payroll
 * clerk may need every web menu and no phone at all, while a field worker is the
 * reverse.
 *
 * Defaults to true so the flag is opt-out: no existing account loses its app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('can_access_mobile')->default(true)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('can_access_mobile');
        });
    }
};
