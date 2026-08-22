<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the company-paid BPJS premiums (JKK, JKM, Kesehatan) join the
     * month's bruto before the TER rate is looked up.
     *
     * PMK 168/2023 says they do — they are the employee's income — so the
     * default keeps the statutory treatment. The switch exists because some
     * payroll desks withhold on the salary alone and reconcile the difference
     * at year end; forcing them to change on the same day the system goes live
     * would make every payslip disagree with their own sheet.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('tax_includes_employer_bpjs')->default(true)->after('require_salary_approval');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('tax_includes_employer_bpjs');
        });
    }
};
