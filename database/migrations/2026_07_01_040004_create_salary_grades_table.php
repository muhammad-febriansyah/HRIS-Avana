<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('grade_code');
            $table->string('grade_name');
            $table->unsignedInteger('level')->default(1);
            $table->decimal('min_salary', 15, 2)->default(0);
            $table->decimal('mid_salary', 15, 2)->default(0);
            $table->decimal('max_salary', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grades');
    }
};
