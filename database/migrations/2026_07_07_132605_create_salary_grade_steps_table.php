<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grade_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_grade_id')->constrained()->cascadeOnDelete();
            // Masa kerja step (in years) within a grade; the "ruang" of a golongan.
            $table->unsignedSmallInteger('masa_kerja')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'salary_grade_id', 'masa_kerja'], 'sgs_grade_step_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grade_steps');
    }
};
