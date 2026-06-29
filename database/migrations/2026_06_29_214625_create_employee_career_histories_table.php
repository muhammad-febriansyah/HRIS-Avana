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
        Schema::create('employee_career_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type'); // mutation | promotion | demotion | transfer | resign | terminate
            $table->date('effective_date');
            $table->foreignId('previous_position_id')->nullable();
            $table->foreignId('position_id')->nullable();
            $table->foreignId('previous_department_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('previous_branch_id')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->string('previous_employment_status')->nullable();
            $table->string('employment_status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_career_histories');
    }
};
