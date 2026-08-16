<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insentif: the money paid on top of salary for what somebody achieved, rather
 * than for the post they hold.
 *
 * Four tables, in the order the flow uses them:
 *
 *   incentive_schemes      what is being paid and out of which payroll
 *                          component, effective from when
 *   incentive_rules        the bands that turn a measured figure into rupiah
 *   incentive_assignments  which employees a scheme applies to, and from when
 *   incentive_calculations one row per employee per scheme per period: the
 *                          amount, the rules and source figures it came from,
 *                          and where it is in review
 *
 * The calculation row is the unit of approval and the unit payroll reads. It
 * carries a snapshot of the rule that produced it, so editing a scheme later
 * never changes what a locked period paid, and it is unique per employee +
 * scheme + period, so a re-run cannot pay the same incentive twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incentive_schemes', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->nullable()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // What the amount is measured from: attendance (present days),
            // performance (review score), target (a figure HR enters per
            // period), or manual (HR types the rupiah itself).
            $table->string('basis')->default('manual');
            // Which earning component the payslip shows it as; its taxable and
            // BPJS flags decide how the money is treated.
            $table->foreignId('payroll_component_id')->nullable()->constrained('payroll_components')->nullOnDelete();
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            // Rounding applied to the computed rupiah, e.g. nearest 1.000.
            $table->string('rounding')->default('none');
            $table->unsignedInteger('rounding_unit')->default(1);
            // Prorate a joiner/leaver by their worked share of the period.
            $table->boolean('prorate_partial_period')->default(false);
            $table->string('status')->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('incentive_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incentive_scheme_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            // The band this rule covers, on the scheme's basis figure.
            $table->decimal('min_value', 15, 2)->nullable();
            $table->decimal('max_value', 15, 2)->nullable();
            // fixed | per_unit | percent_of_basic
            $table->string('amount_type')->default('fixed');
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['incentive_scheme_id', 'sequence']);
        });

        Schema::create('incentive_assignments', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->nullable()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incentive_scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One live assignment per employee per scheme per start date: the
            // same scheme handed to the same person twice would pay twice.
            $table->unique(['incentive_scheme_id', 'employee_id', 'effective_start_date'], 'incentive_assignment_unique');
        });

        Schema::create('incentive_calculations', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 32)->nullable()->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incentive_scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('measured_value', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            // Set when HR overrides the computed figure; the computed one stays
            // beside it so the override is visible rather than silent.
            $table->decimal('computed_amount', 15, 2)->nullable();
            $table->json('source_snapshot')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            // The duplicate guard payroll relies on: one incentive per employee
            // per scheme per period, however many times payroll is re-run.
            $table->unique(['incentive_scheme_id', 'employee_id', 'payroll_period_id'], 'incentive_calculation_unique');
            $table->index(['tenant_id', 'payroll_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_calculations');
        Schema::dropIfExists('incentive_assignments');
        Schema::dropIfExists('incentive_rules');
        Schema::dropIfExists('incentive_schemes');
    }
};
