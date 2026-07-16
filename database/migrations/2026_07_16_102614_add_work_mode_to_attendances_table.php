<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the employee says they worked from on a given clock-in: 'office' or
 * 'home'. Distinct from location_status, which is the geofence verdict — this
 * is the declaration, that is the outcome.
 *
 * Defaults to 'office', so every existing row reads as it always did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->string('work_mode', 10)->default('office')->after('location_status');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn('work_mode');
        });
    }
};
