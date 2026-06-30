<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talent_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('performance_level')->default('medium'); // low|medium|high
            $table->string('potential_level')->default('medium'); // low|medium|high
            $table->text('note')->nullable();
            $table->string('successor_for')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talent_assessments');
    }
};
