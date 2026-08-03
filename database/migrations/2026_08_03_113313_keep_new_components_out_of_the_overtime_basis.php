<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stop a new payroll component joining the overtime basis by accident.
 *
 * `is_fixed` marks the allowances that join Gaji Pokok in the overtime basis
 * (PP 35/2021 Pasal 30), and it defaulted to true. An earlier migration
 * normalised the rows that existed at the time, but the default outlived it:
 * every component created afterwards — by Master Komponen, by a seeder, by a
 * new tenant being provisioned — was born inside the basis. A per-day meal
 * allowance, a co-op deduction and "Uang Lembur" itself all counted, so the
 * hourly wage overtime was paid at was computed from more than the wage, and
 * the overtime line fed the basis that produced it.
 *
 * The default becomes false, and the same normalisation runs again over what
 * the seeders have created since.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->boolean('is_fixed')->default(false)->change();
        });

        // Deductions are never part of a wage basis.
        DB::table('payroll_components')
            ->where(fn ($query) => $query->where('type', 'deduction')->orWhere('component_group', 'potongan'))
            ->update(['is_fixed' => false]);

        // Attendance-variable earnings are by definition not a fixed allowance.
        DB::table('payroll_components')
            ->whereIn('calc_basis', ['per_present_day', 'per_overtime_hour'])
            ->update(['is_fixed' => false]);

        // The overtime line is the result of the basis, never part of it.
        DB::table('payroll_components')
            ->where('code', 'LEMBUR')
            ->update(['is_fixed' => false]);

        // Gaji Pokok always counts — the design pins it as "selalu ikut".
        DB::table('payroll_components')
            ->where('code', 'BASIC')
            ->update(['is_fixed' => true]);
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->boolean('is_fixed')->default(true)->change();
        });
    }
};
