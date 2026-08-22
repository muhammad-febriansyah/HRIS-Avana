<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Third hardening pass, closing the separation-of-duties hole left by the
 * second one:
 *
 * - records *who* scored a review as its manager and when, so calibration can
 *   refuse the same person signing both halves of the appraisal;
 * - makes the manual-vs-KPI decision explicit instead of inferring it from
 *   whether any KPI item happens to exist right now;
 * - pins `final_score = calibrated_score` as a database invariant rather than a
 *   convention the workflow happens to follow.
 */
return new class extends Migration
{
    /**
     * The invariant added here, as `name => expression`.
     */
    private const FINAL_MATCHES_CALIBRATED = 'performance_reviews_final_matches_calibrated_check';

    public function up(): void
    {
        if (! Schema::hasColumn('performance_reviews', 'manager_scored_by')) {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->foreignId('manager_scored_by')->nullable()->after('manager_score')->constrained('users')->nullOnDelete();
                $table->timestamp('manager_scored_at')->nullable()->after('manager_scored_by');
                // manual|kpi — which scoring model this review is committed to.
                $table->string('scoring_mode')->default('manual')->after('status');
            });
        }

        // Reviews that already carry KPI items were scored by the KPI engine,
        // whatever their items look like now.
        DB::table('performance_reviews')
            ->whereIn('id', DB::table('performance_kpi_items')->select('review_id'))
            ->update(['scoring_mode' => 'kpi']);

        if ($this->supportsAddingChecks() && ! $this->checkExists()) {
            DB::statement(
                'alter table performance_reviews add constraint '.self::FINAL_MATCHES_CALIBRATED
                .' check (calibrated_score is null or final_score = calibrated_score)'
            );
        }
    }

    public function down(): void
    {
        if ($this->supportsAddingChecks() && $this->checkExists()) {
            DB::statement('alter table performance_reviews drop constraint '.self::FINAL_MATCHES_CALIBRATED);
        }

        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_scored_by');
            $table->dropColumn(['manager_scored_at', 'scoring_mode']);
        });
    }

    private function checkExists(): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->where('CONSTRAINT_NAME', self::FINAL_MATCHES_CALIBRATED)
            ->exists();
    }

    /**
     * SQLite cannot add a CHECK constraint to an existing table, so the test
     * connection relies on the application-layer enforcement alone.
     */
    private function supportsAddingChecks(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
