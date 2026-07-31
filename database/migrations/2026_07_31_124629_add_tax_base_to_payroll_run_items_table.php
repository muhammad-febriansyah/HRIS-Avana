<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each month contributed to the year's tax, kept beside what it paid.
 *
 * December reconciles the year against Pasal 17, and to do that it has to add
 * up the earlier months. It was summing `gross_salary` — the payslip figure,
 * which includes non-taxable earnings and excludes the company-paid premiums
 * that ARE the employee's income. So the annual base disagreed with the base
 * the monthly TER had actually been charged on.
 *
 * These two columns record the numbers the tax was worked out from, so the
 * December sum is the same measure all year and an auditor can follow it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->decimal('taxable_gross', 15, 2)->default(0)->after('gross_salary');
            // Employee JHT + JP for the month: deductible from gross at year end,
            // but never from the monthly TER base.
            $table->decimal('tax_deductible_premium', 15, 2)->default(0)->after('taxable_gross');
        });

        // Runs already closed keep their figures; the payslip gross is the best
        // record we have of what those months were taxed on.
        DB::table('payroll_run_items')->update([
            'taxable_gross' => DB::raw('gross_salary'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->dropColumn(['taxable_gross', 'tax_deductible_premium']);
        });
    }
};
