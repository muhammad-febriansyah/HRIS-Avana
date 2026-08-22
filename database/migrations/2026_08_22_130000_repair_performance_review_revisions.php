<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `performance_review_revisions` to its final shape on databases that
 * already ran the first hardening migration.
 *
 * That migration originally created the table with `review_id` cascading on
 * delete, which defeats the table's whole purpose: a review that was completed,
 * reopened, and then deleted would take the record of its superseded rating
 * with it. The definition there has since been corrected for fresh installs;
 * this migration performs the equivalent change in place, and also creates the
 * table outright if an interrupted rollback removed it.
 *
 * MySQL cannot alter a foreign key's referential action, and SQLite cannot
 * alter foreign keys at all, so the table is rebuilt and its rows copied over.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_review_revisions')) {
            $this->createTable('performance_review_revisions');

            return;
        }

        if (Schema::hasColumn('performance_review_revisions', 'employee_id')) {
            return;
        }

        $this->createTable('performance_review_revisions_new');

        // Column list is explicit: the old table has no employee_id/cycle_id,
        // and those are backfilled from the review the revision points at.
        $rows = Schema::getConnection()->table('performance_review_revisions')->get();

        foreach ($rows as $row) {
            $review = Schema::getConnection()->table('performance_reviews')->find($row->review_id);

            Schema::getConnection()->table('performance_review_revisions_new')->insert([
                'tenant_id' => $row->tenant_id,
                'review_id' => $row->review_id,
                'employee_id' => $review->employee_id ?? null,
                'cycle_id' => $review->cycle_id ?? null,
                'reopened_by' => $row->reopened_by,
                'from_status' => $row->from_status,
                'to_status' => $row->to_status,
                'self_score' => $row->self_score,
                'manager_score' => $row->manager_score,
                'final_score' => $row->final_score,
                'calibrated_score' => $row->calibrated_score,
                'calibrated_by' => $row->calibrated_by,
                'calibrated_at' => $row->calibrated_at,
                'reason' => $row->reason,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }

        Schema::drop('performance_review_revisions');
        Schema::rename('performance_review_revisions_new', 'performance_review_revisions');
    }

    public function down(): void
    {
        // The corrected shape is a superset of the original; reverting it would
        // discard the denormalised columns for no gain.
    }

    private function createTable(string $name): void
    {
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
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
    }
};
