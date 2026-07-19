<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routes a reimbursement to the employee's own line manager, the way leave,
 * overtime and settlements already do. The mobile approval queue selects on
 * this column, so without it a reimbursement filed from the app can never
 * reach anyone's queue.
 *
 * `migrated_claim_id` traces a row back to the `claims` record it was folded
 * in from, which keeps that copy idempotent and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursements', function (Blueprint $table): void {
            $table->foreignId('current_approver_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('employees')
                ->nullOnDelete();

            $table->unsignedBigInteger('migrated_claim_id')->nullable()->after('number');
            $table->index('migrated_claim_id');
        });
    }

    public function down(): void
    {
        Schema::table('reimbursements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_approver_id');
            $table->dropIndex(['migrated_claim_id']);
            $table->dropColumn('migrated_claim_id');
        });
    }
};
