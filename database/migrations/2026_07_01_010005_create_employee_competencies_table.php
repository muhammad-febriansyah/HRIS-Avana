<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1); // 1-5
            $table->date('assessed_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_competencies');
    }
};
