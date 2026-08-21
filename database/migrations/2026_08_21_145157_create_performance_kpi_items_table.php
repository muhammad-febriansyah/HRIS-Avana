<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_kpi_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('kpi_indicator_id')->nullable()->constrained('kpi_indicators')->nullOnDelete();
            $table->string('source')->default('manual'); // manual|key_result
            $table->foreignId('key_result_id')->nullable()->constrained('key_results')->nullOnDelete();
            $table->string('label'); // snapshot of indicator name / key result title at add-time
            $table->decimal('weight', 5, 2)->default(0); // % share within the review
            $table->string('direction')->default('higher_better'); // snapshot, independent of indicator's current direction
            $table->decimal('target_value', 12, 2)->nullable();
            $table->decimal('actual_value', 12, 2)->nullable();
            $table->decimal('achievement_pct', 6, 2)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'review_id']);
            $table->index(['key_result_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_kpi_items');
    }
};
