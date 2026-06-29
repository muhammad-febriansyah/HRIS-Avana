<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->string('calc_basis')->nullable()->default('fixed')->after('is_fixed');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropColumn('calc_basis');
        });
    }
};
