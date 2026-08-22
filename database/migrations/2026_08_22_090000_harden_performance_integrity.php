<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Second hardening pass on the performance appraisal domain:
 *
 * - quarantines legacy `completed` reviews that were never calibrated, so they
 *   stop being read as publishable final ratings downstream;
 * - snapshots a review's finalized scores whenever it is reopened, instead of
 *   silently leaving stale calibration data attached;
 * - snapshots the KPI indicator's unit onto the item, so the number keeps its
 *   meaning after the master indicator is edited;
 * - adds the invariants the application layer now enforces as real database
 *   CHECK constraints, on drivers that support adding them after the fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each step is guarded: MySQL has no transactional DDL, so a failure
        // part-way through leaves the earlier statements applied and this
        // migration has to be safe to re-run over that state.
        if (! Schema::hasColumn('performance_reviews', 'is_legacy')) {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->boolean('is_legacy')->default(false)->after('status');
                $table->index(['tenant_id', 'status', 'is_legacy'], 'performance_reviews_publishable_index');
            });
        }

        // Reviews marked `completed` before calibration became mandatory carry
        // a final_score nobody signed off on. They are quarantined rather than
        // back-filled: inventing a calibrator would be worse than admitting the
        // rating is unverified.
        DB::table('performance_reviews')
            ->where('status', 'completed')
            ->where(function ($query): void {
                $query->whereNull('calibrated_score')
                    ->orWhereNull('calibrated_at')
                    ->orWhereNull('final_score');
            })
            ->update(['is_legacy' => true]);

        if (! Schema::hasColumn('performance_kpi_items', 'unit')) {
            Schema::table('performance_kpi_items', function (Blueprint $table): void {
                $table->string('unit')->nullable()->after('label');
                $table->unique(['review_id', 'key_result_id'], 'performance_kpi_items_review_key_result_unique');
                $table->unique(['review_id', 'kpi_indicator_id'], 'performance_kpi_items_review_indicator_unique');
            });
        }

        if (Schema::hasTable('performance_review_revisions')) {
            $this->applyCheckConstraints();

            return;
        }

        Schema::create('performance_review_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Deliberately NOT cascading: the whole point of a revision is to
            // outlive the row it describes. A review that was completed, then
            // reopened, then deleted would otherwise take the record of its own
            // superseded rating with it. `employee_id`/`cycle_id` are kept
            // alongside so the orphaned revision still says who and when.
            $table->foreignId('review_id')->nullable()->constrained('performance_reviews')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('performance_cycles')->nullOnDelete();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('manager_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->decimal('calibrated_score', 5, 2)->nullable();
            $table->foreignId('calibrated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calibrated_at')->nullable();
            $table->text('reason');
            $table->timestamps();
            $table->index(['tenant_id', 'review_id']);
        });

        $this->applyCheckConstraints();
    }

    public function down(): void
    {
        $this->dropCheckConstraints();

        Schema::dropIfExists('performance_review_revisions');

        // Guarded the same way `up()` is: MySQL applies DDL statement by
        // statement, so a run that failed part-way leaves this migration facing
        // a half-applied schema on the way back down too.
        $kpiIndexes = $this->indexNames('performance_kpi_items');

        // MySQL uses the composite unique starting with `review_id` as the
        // index backing that column's foreign key, and refuses to drop it while
        // it is the only candidate. Give the FK its own index first.
        if (! in_array('performance_kpi_items_review_id_index', $kpiIndexes, true)) {
            Schema::table('performance_kpi_items', function (Blueprint $table): void {
                $table->index(['review_id'], 'performance_kpi_items_review_id_index');
            });
        }

        Schema::table('performance_kpi_items', function (Blueprint $table) use ($kpiIndexes): void {
            foreach ([
                'performance_kpi_items_review_key_result_unique',
                'performance_kpi_items_review_indicator_unique',
            ] as $index) {
                if (in_array($index, $kpiIndexes, true)) {
                    $table->dropUnique($index);
                }
            }

            if (Schema::hasColumn('performance_kpi_items', 'unit')) {
                $table->dropColumn('unit');
            }
        });

        $reviewIndexes = $this->indexNames('performance_reviews');

        Schema::table('performance_reviews', function (Blueprint $table) use ($reviewIndexes): void {
            if (in_array('performance_reviews_publishable_index', $reviewIndexes, true)) {
                $table->dropIndex('performance_reviews_publishable_index');
            }

            if (Schema::hasColumn('performance_reviews', 'is_legacy')) {
                $table->dropColumn('is_legacy');
            }
        });
    }

    /**
     * The domain invariants, as `name => expression` pairs per table.
     *
     * @return array<string, array<string, string>>
     */
    private function constraints(): array
    {
        return [
            'performance_reviews' => [
                'performance_reviews_status_check' => "status in ('pending','self_review','manager_review','calibration','completed')",
                'performance_reviews_scores_range_check' => '(self_score is null or (self_score >= 0 and self_score <= 100)) and (manager_score is null or (manager_score >= 0 and manager_score <= 100)) and (final_score is null or (final_score >= 0 and final_score <= 100)) and (calibrated_score is null or (calibrated_score >= 0 and calibrated_score <= 100))',
                // A completed review is either quarantined legacy data, or it
                // carries a calibration record.
                //
                // `calibrated_by` is deliberately absent: MySQL forbids a column
                // used by an `on delete set null` foreign key from appearing in
                // a CHECK, and that FK is exactly why the column is unreliable —
                // deleting the calibrator's user account must not retroactively
                // invalidate a signed rating. `calibrated_at` is the durable
                // marker that calibration happened; `calibrated_by` is
                // attribution, which the revision history also records.
                'performance_reviews_completed_calibrated_check' => "status <> 'completed' or is_legacy = true or (final_score is not null and calibrated_score is not null and calibrated_at is not null)",
            ],
            'performance_cycles' => [
                'performance_cycles_status_check' => "status in ('draft','active','closed')",
                'performance_cycles_period_check' => 'period_end >= period_start',
            ],
            'performance_kpi_items' => [
                'performance_kpi_items_source_check' => "source in ('manual','key_result')",
                'performance_kpi_items_direction_check' => "direction in ('higher_better','lower_better')",
                'performance_kpi_items_weight_check' => 'weight >= 0 and weight <= 100',
                // The `source ⇒ link` pairing cannot be a CHECK for the same
                // MySQL reason as above: both link columns carry `on delete set
                // null` foreign keys. It is enforced in the application instead
                // — PerformanceKpiItemController writes the pair atomically, and
                // OkrController/KpiIndicatorController refuse to delete a Key
                // Result, Objective, or indicator that a KPI item still points
                // at, so the null-on-delete path is unreachable in practice.
            ],
        ];
    }

    private function applyCheckConstraints(): void
    {
        if (! $this->supportsAddingChecks()) {
            return;
        }

        $existing = $this->existingCheckNames();

        foreach ($this->constraints() as $table => $checks) {
            foreach ($checks as $name => $expression) {
                if (in_array($name, $existing, true)) {
                    continue;
                }

                DB::statement("alter table {$table} add constraint {$name} check ({$expression})");
            }
        }
    }

    /**
     * Index names currently defined on the given table.
     *
     * @return array<int, string>
     */
    private function indexNames(string $table): array
    {
        return Schema::getIndexListing($table);
    }

    /**
     * Names of the CHECK constraints already present on this connection.
     *
     * @return array<int, string>
     */
    private function existingCheckNames(): array
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->pluck('CONSTRAINT_NAME')
            ->map(fn (string $name): string => $name)
            ->all();
    }

    private function dropCheckConstraints(): void
    {
        if (! $this->supportsAddingChecks()) {
            return;
        }

        $existing = $this->existingCheckNames();

        foreach ($this->constraints() as $table => $checks) {
            foreach (array_keys($checks) as $name) {
                if (in_array($name, $existing, true)) {
                    DB::statement("alter table {$table} drop constraint {$name}");
                }
            }
        }
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
