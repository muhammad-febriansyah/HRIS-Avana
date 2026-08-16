<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the component paid before this version took over.
 *
 * The figure was always recoverable — the previous row is closed, not deleted —
 * but only by re-deriving the timeline. A raise is questioned as "from what, to
 * what, why, and on whose say-so", and three of those four already sat on the
 * row; this puts the fourth beside them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->decimal('previous_amount', 15, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->dropColumn('previous_amount');
        });
    }
};
