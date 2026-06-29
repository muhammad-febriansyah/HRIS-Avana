<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('bpjs_programs')->cascadeOnDelete();
            $table->decimal('employee_rate', 6, 4)->default(0);
            $table->decimal('company_rate', 6, 4)->default(0);
            $table->decimal('max_wage', 15, 2)->nullable();
            $table->decimal('min_wage', 15, 2)->nullable();
            $table->string('risk_level')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_rates');
    }
};
