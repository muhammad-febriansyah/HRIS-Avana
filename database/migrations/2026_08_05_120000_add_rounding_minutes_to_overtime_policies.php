<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a tenant turns clocked overtime minutes into payable hours.
 *
 * Companies count the tail of an overtime stretch differently: some pay the
 * exact minutes, many round down to the nearest half hour and drop anything
 * shorter. Zero keeps the old behaviour (exact decimal hours), so nothing
 * changes for a tenant that has not set a rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_policies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('rounding_minutes')->default(0)->after('hours_divisor');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_policies', function (Blueprint $table): void {
            $table->dropColumn('rounding_minutes');
        });
    }
};
