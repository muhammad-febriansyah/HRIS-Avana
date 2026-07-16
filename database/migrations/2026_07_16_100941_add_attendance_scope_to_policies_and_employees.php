<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How far from their assigned office an employee may clock in:
 *
 *   assigned   — their own work location / branch only (the existing behaviour)
 *   any_branch — any active work location in the tenant, radius still enforced
 *   anywhere   — WFA: the radius is not enforced at all
 *
 * The tenant policy carries the default; an employee row may override it, so
 * field staff can be given WFA without loosening it for everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->string('attendance_scope', 20)->default('assigned')->after('tenant_id');
        });

        Schema::table('employees', function (Blueprint $table): void {
            // Null means "follow the tenant policy" — the default for everyone,
            // so this migration cannot change how any existing employee clocks in.
            $table->string('attendance_scope', 20)->nullable()->after('work_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_policies', function (Blueprint $table): void {
            $table->dropColumn('attendance_scope');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('attendance_scope');
        });
    }
};
