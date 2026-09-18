<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roster_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('shift_label')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'employee_id', 'period_start', 'period_end', 'status'],
                'roster_plans_employee_period_status_unique',
            );
            $table->index(['tenant_id', 'status', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_plans');
    }
};
