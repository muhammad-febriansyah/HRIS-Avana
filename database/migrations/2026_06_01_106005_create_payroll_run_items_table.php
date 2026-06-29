<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('total_allowance', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('bpjs_employee_total', 15, 2)->default(0);
            $table->decimal('bpjs_company_total', 15, 2)->default(0);
            $table->decimal('pph21_total', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->string('status')->default('calculated')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'payroll_run_id']);
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'payroll_period_id', 'employee_id']);
            $table->unique(['payroll_run_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_items');
    }
};
