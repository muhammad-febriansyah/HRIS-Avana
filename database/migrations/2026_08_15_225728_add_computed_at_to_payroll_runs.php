<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this run's figures were computed.
 *
 * Staleness was measured from the run items' `updated_at`, but a recomputation
 * that produces identical figures does not touch those rows — so a period could
 * stay flagged as stale forever, with no way to approve it. The run records the
 * moment it was calculated instead, which moves on every recomputation whether
 * or not any number changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->timestamp('computed_at')->nullable()->after('reconciliation');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropColumn('computed_at');
        });
    }
};
