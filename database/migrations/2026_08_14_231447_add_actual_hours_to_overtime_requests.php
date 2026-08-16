<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime was paid on the hours somebody asked for and a manager approved,
 * with nothing checking whether the employee was still at work for them. The
 * approved figure stays as the request; the hours actually worked past the end
 * of the shift, and the hours payroll pays for, are recorded beside it so a
 * payslip line can be explained without re-deriving it from attendance months
 * later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->decimal('actual_hours', 6, 2)->nullable()->after('hours');
            $table->decimal('payable_hours', 6, 2)->nullable()->after('actual_hours');
            $table->string('payable_basis')->nullable()->after('payable_hours');
            $table->timestamp('hours_verified_at')->nullable()->after('payable_basis');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->dropColumn(['actual_hours', 'payable_hours', 'payable_basis', 'hours_verified_at']);
        });
    }
};
