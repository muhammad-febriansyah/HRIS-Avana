<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who raised a pay correction or a rapel.
 *
 * Both add money to a payslip and both recorded only who approved them, so one
 * person could raise and sign off the same payment and nothing on the row would
 * show it. The maker is stored beside the checker.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payroll_corrections', 'salary_rapels'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('created_by')->nullable()->after('status')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['payroll_corrections', 'salary_rapels'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('created_by');
            });
        }
    }
};
