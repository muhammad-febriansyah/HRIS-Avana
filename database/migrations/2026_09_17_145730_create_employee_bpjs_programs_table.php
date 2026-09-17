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
        Schema::create('employee_bpjs_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('bpjs_programs')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'employee_id', 'program_id']);
            $table->index(['tenant_id', 'employee_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_bpjs_programs');
    }
};
