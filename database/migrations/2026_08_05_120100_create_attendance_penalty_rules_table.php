<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant tiers for attendance penalties — "10–30 menit: Rp20.000,
 * 30–60 menit: Rp50.000" and whatever else a company writes into its own
 * regulation. Without them a penalty could only be typed in by hand, one
 * employee at a time, with the amount remembered rather than configured.
 *
 * `max_minutes` null means the last, open-ended tier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_penalty_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('violation_type')->default('late');
            $table->unsignedInteger('min_minutes')->default(0);
            $table->unsignedInteger('max_minutes')->nullable();
            $table->string('penalty_type')->default('deduction');
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Named by hand: the generated name overruns MySQL's 64-character
            // identifier limit.
            $table->index(['tenant_id', 'violation_type', 'min_minutes'], 'apr_tenant_violation_min_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_penalty_rules');
    }
};
