<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a payroll run's figures came from, and how they compare with the data
 * the system holds.
 *
 * A computed run and an uploaded one look identical once stored, so an imported
 * payroll — which never passes through salary versions, actual overtime,
 * approved incentives, corrections or deductions — could be approved and locked
 * as if the engine had produced it. It is a different flow and is now labelled
 * as one: `source` says which, and `reconciliation` keeps the comparison
 * against the salaries on file that the approver has to answer for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('source')->default('engine')->after('status')->index();
            $table->json('reconciliation')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropColumn(['source', 'reconciliation']);
        });
    }
};
