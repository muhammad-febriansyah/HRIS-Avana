<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary changes behind an approval, per the documented status flow
 * (DRAFT → PENDING_APPROVAL → ACTIVE) and its audit requirements.
 *
 * The tenant decides whether approval applies at all; where it does, a new
 * salary version is written pending and pays nothing until someone other than
 * its author approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('require_salary_approval')->default(false)->after('enforce_payroll_segregation');
        });

        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->foreignId('approved_by')->nullable()->after('updated_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('require_salary_approval');
        });

        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
