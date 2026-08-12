<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->string('source_type', 32)->default('employee_override')->after('salary_change_set_id');
            $table->index(
                ['tenant_id', 'employee_id', 'source_type', 'status'],
                'employee_salary_source_status_index',
            );
        });

        Schema::table('salary_change_sets', function (Blueprint $table): void {
            $table->string('change_type', 32)->default('individual')->after('salary_master_id');
            $table->string('existing_strategy', 16)->nullable()->after('change_type');
        });
    }

    public function down(): void
    {
        Schema::table('salary_change_sets', function (Blueprint $table): void {
            $table->dropColumn(['change_type', 'existing_strategy']);
        });

        Schema::table('employee_salary_components', function (Blueprint $table): void {
            $table->dropIndex('employee_salary_source_status_index');
            $table->dropColumn('source_type');
        });
    }
};
