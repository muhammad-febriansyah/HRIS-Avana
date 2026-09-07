<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a payslip becomes readable by the employee it belongs to: H-1 before
 * their own pay date.
 *
 * Stored per row rather than derived at read time, because the pay date is the
 * employee's payday group's — two groups in one period are paid on different
 * days, so the period's single pay_date cannot answer for both. Freezing it
 * with the run also makes the release date auditable: it says what the slip
 * was scheduled for, not what today's configuration would recompute.
 *
 * Existing rows stay null, which reads as "released once the run is locked" —
 * the rule in force before this column existed, so no payslip an employee has
 * already been shown disappears.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->date('released_at')->nullable()->after('status');
            $table->index(['tenant_id', 'employee_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'employee_id', 'released_at']);
            $table->dropColumn('released_at');
        });
    }
};
