<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime is filed as a time range now, not a bare number of hours: "18:00 to
 * 20:00" is what the employee actually worked and what an approver can check,
 * while "2" is a claim nobody can verify.
 *
 * `hours` stays and is still what payroll reads — it is now computed from the
 * range rather than typed. Nullable because requests filed before this change
 * have no range to backfill, and inventing one would be a lie about when
 * somebody worked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->time('start_time')->nullable()->after('date');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
