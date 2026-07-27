<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee of the Month voting.
 *
 * A period is one month of voting that HR opens and closes. While it is open
 * each employee casts exactly one vote — for a colleague, not themselves —
 * naming the core value that colleague showed. Closing a period freezes the
 * tally and stamps the winner, so past months stay auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The core values a voter picks from, e.g. Jujur, Kerjasama, Integritas.
        Schema::create('eotm_core_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->default('sparkles');
            $table->string('color')->default('#7C3AED');
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('eotm_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // `YYYY-MM` — one period per tenant per month.
            $table->string('period', 7);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // draft -> open -> closed. Only `open` accepts votes.
            $table->string('status')->default('draft');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            // Stamped when the period closes, so the result survives later votes
            // being deleted or employees leaving.
            $table->foreignId('winner_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('winner_votes')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('eotm_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eotm_period_id')->constrained()->cascadeOnDelete();
            // Who voted, and who they voted for.
            $table->foreignId('voter_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('nominee_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('eotm_core_value_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            // One vote per employee per period — this index is the rule.
            $table->unique(['eotm_period_id', 'voter_employee_id']);
            $table->index(['eotm_period_id', 'nominee_employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eotm_votes');
        Schema::dropIfExists('eotm_periods');
        Schema::dropIfExists('eotm_core_values');
    }
};
