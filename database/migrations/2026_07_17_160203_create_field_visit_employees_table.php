<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several employees can attend the same visit, so who was there becomes a
 * has-many-through this pivot.
 *
 * `field_visits.employee_id` stays as the employee the visit is filed under —
 * the ESS/Flutter app reports and lists by it, and every photo hangs off it —
 * and is mirrored here so "who attended" is a single query rather than a union
 * of the column and the pivot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_visit_employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_visit_id')->constrained('field_visits')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['field_visit_id', 'employee_id']);
            $table->index(['tenant_id', 'employee_id']);
        });

        // Every existing visit already has exactly one attendee: its owner.
        DB::statement('
            INSERT INTO field_visit_employees (tenant_id, field_visit_id, employee_id, created_at, updated_at)
            SELECT tenant_id, id, employee_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM field_visits
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visit_employees');
    }
};
