<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_change_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('salary_master_id')->nullable()->constrained('salary_masters')->nullOnDelete();
            $table->date('effective_start_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'status']);
        });

        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->foreignId('salary_change_set_id')->nullable()->after('salary_master_id')
                ->constrained('salary_change_sets')->nullOnDelete();
            $table->index(['tenant_id', 'salary_change_set_id']);
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'salary_change_set_id']);
            $table->dropConstrainedForeignId('salary_change_set_id');
        });

        Schema::dropIfExists('salary_change_sets');
    }
};
