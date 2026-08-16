<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A finalised payroll becomes a revision, not a draft to be overwritten.
 *
 * Unlocking a period used to hand the same run back to the engine, so the next
 * calculation replaced the very figures the payslips had already stated — the
 * period's history collapsed to whatever was computed last. Now the locked run
 * is closed off (`superseded_at`) and kept, and recalculation opens the next
 * `revision`, pointing back at the one it replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->unsignedInteger('revision')->default(1)->after('source');
            $table->timestamp('superseded_at')->nullable()->after('revision')->index();
            $table->foreignId('superseded_by')->nullable()->after('superseded_at')
                ->constrained('payroll_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('superseded_by');
            $table->dropColumn(['revision', 'superseded_at']);
        });
    }
};
