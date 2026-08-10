<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a salary version is worth is only half the record. The setup
 * documentation also asks for why it changed, who changed it, which contract it
 * belongs to, which Master Gaji it came from, and whether it is in force yet —
 * without those a raise cannot be audited or approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->foreignId('employee_contract_id')->nullable()->after('employee_id')
                ->constrained('employee_contracts')->nullOnDelete();
            $table->foreignId('salary_master_id')->nullable()->after('employee_contract_id')
                ->constrained('salary_masters')->nullOnDelete();
            $table->string('status')->default('active')->after('amount');
            $table->string('reason')->nullable()->after('effective_end_date');
            $table->foreignId('created_by')->nullable()->after('reason')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();

            $table->index(['tenant_id', 'employee_id', 'status'], 'esc_tenant_employee_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->dropIndex('esc_tenant_employee_status_index');
            $table->dropConstrainedForeignId('employee_contract_id');
            $table->dropConstrainedForeignId('salary_master_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['status', 'reason']);
        });
    }
};
