<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The wall clock a tenant works to.
 *
 * Indonesia spans three zones, and the application had one: everything was
 * WIB. A Makassar office clocking in at 08:00 was recorded as 07:00 and read
 * back an hour early against its own shift.
 *
 * Existing tenants keep WIB, which is what they have been running on, so the
 * column changes nothing until someone sets it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('timezone')->default('Asia/Jakarta')->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
