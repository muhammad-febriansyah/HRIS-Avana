<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speed up the per-employee, date-ordered paginated visit list
     * (`where employee_id … order by visit_date desc`).
     */
    public function up(): void
    {
        Schema::table('field_visits', function (Blueprint $table): void {
            $table->index(['employee_id', 'visit_date'], 'field_visits_employee_visit_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('field_visits', function (Blueprint $table): void {
            $table->dropIndex('field_visits_employee_visit_date_index');
        });
    }
};
