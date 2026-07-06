<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Dasar Perhitungan" (BPR manual 1.2.2): how a component derives its base
 * amount. `basis_type` null keeps the legacy behaviour (per-employee /
 * per-position amount x attendance calc_basis); otherwise:
 *   - fixed   -> basis_value
 *   - tabel   -> the most-specific Nilai Komponen mapping row (payroll_component_values)
 *   - formula -> evaluated Master Formula (payroll_formula_id), clamped to min/max
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->string('basis_type')->nullable()->after('calc_basis'); // fixed|tabel|formula
            $table->decimal('basis_value', 15, 2)->nullable()->after('basis_type');
            $table->decimal('basis_min', 15, 2)->nullable()->after('basis_value');
            $table->decimal('basis_max', 15, 2)->nullable()->after('basis_min');
            $table->unsignedTinyInteger('basis_cut_off_day')->nullable()->after('basis_max');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropColumn(['basis_type', 'basis_value', 'basis_min', 'basis_max', 'basis_cut_off_day']);
        });
    }
};
