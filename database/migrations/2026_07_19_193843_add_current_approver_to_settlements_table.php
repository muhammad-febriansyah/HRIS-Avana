<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routes a submitted settlement to the employee's own line manager, the way
 * leave, overtime and reimbursement already do. Without it the manager desk was
 * open to anyone with claim permission and closed to actual managers, and the
 * mobile approval queue — which selects on `current_approver_id` — could not
 * see settlements at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->foreignId('current_approver_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_approver_id');
        });
    }
};
