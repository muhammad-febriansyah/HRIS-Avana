<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_master_bpjs_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_master_id')->constrained('salary_masters')->cascadeOnDelete();
            $table->foreignId('bpjs_program_id')->constrained('bpjs_programs')->cascadeOnDelete();
            $table->boolean('included')->default(true);
            $table->unique(['tenant_id', 'salary_master_id', 'bpjs_program_id'], 'salary_master_bpjs_program_unique');
            $table->index(['tenant_id', 'salary_master_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_master_bpjs_programs');
    }
};
