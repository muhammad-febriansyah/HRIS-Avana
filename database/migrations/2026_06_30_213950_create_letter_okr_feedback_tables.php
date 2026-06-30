<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- HR letter templates (Template Surat) ----
        Schema::create('letter_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('custom'); // kontrak|sk|paklaring|referensi|custom
            $table->longText('body'); // supports {{placeholder}} tokens
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('generated_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('letter_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('letter_number')->nullable();
            $table->string('title');
            $table->longText('body'); // rendered HTML/text
            $table->date('generated_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });

        // ---- OKR (objectives + key results) ----
        Schema::create('objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('performance_cycles')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('level')->default('individual'); // company|team|individual
            $table->string('status')->default('active'); // draft|active|done|cancelled
            $table->unsignedTinyInteger('progress')->default(0); // 0-100 (rollup of key results)
            $table->timestamps();
            $table->index(['tenant_id', 'cycle_id']);
        });

        Schema::create('key_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('objective_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('target_value', 12, 2)->default(100);
            $table->decimal('current_value', 12, 2)->default(0);
            $table->string('unit')->nullable(); // %, Rp, unit, etc
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->timestamps();
            $table->index(['tenant_id', 'objective_id']);
        });

        // ---- 360 feedback on performance reviews ----
        Schema::create('performance_feedbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('type')->default('peer'); // self|peer|manager|subordinate
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_feedbacks');
        Schema::dropIfExists('key_results');
        Schema::dropIfExists('objectives');
        Schema::dropIfExists('generated_letters');
        Schema::dropIfExists('letter_templates');
    }
};
