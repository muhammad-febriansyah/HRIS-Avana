<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rapel (retroactive salary adjustment): the monthly difference between the
     * new and old nominal of a salary component, back-paid for the months
     * elapsed since it took effect and posted into the current payroll period.
     */
    public function up(): void
    {
        Schema::create('salary_rapels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->decimal('old_amount', 15, 2)->default(0);
            $table->decimal('new_amount', 15, 2)->default(0);
            $table->date('effective_from');
            $table->date('posting_date');
            $table->string('reason');
            $table->string('status')->default('pending'); // pending|approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
            $table->index(['tenant_id', 'posting_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_rapels');
    }
};
