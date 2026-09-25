<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_master_components', function (Blueprint $table): void {
            $table->boolean('is_bpjs_exempt')->default(false)->after('included');
        });

        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->boolean('is_bpjs_exempt')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('salary_master_components', function (Blueprint $table): void {
            $table->dropColumn('is_bpjs_exempt');
        });

        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->dropColumn('is_bpjs_exempt');
        });
    }
};
