<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Izin can now span more than one day, so the single `date` becomes a
 * start..end range — matching how leave_requests and wfh_requests already
 * model theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_requests', function (Blueprint $table): void {
            $table->renameColumn('date', 'start_date');
        });

        Schema::table('permission_requests', function (Blueprint $table): void {
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Existing rows are single-day: the range collapses onto its start.
        DB::table('permission_requests')
            ->whereNull('end_date')
            ->update(['end_date' => DB::raw('start_date')]);

        Schema::table('permission_requests', function (Blueprint $table): void {
            $table->date('end_date')->nullable(false)->change();
            $table->index(['tenant_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('permission_requests', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'start_date', 'end_date']);
            $table->dropColumn('end_date');
        });

        Schema::table('permission_requests', function (Blueprint $table): void {
            $table->renameColumn('start_date', 'date');
        });
    }
};
