<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            // Who the agenda is for: a specific employee and/or a department.
            $table->foreignId('employee_id')->nullable()->after('type')->constrained('employees')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('employee_id')->constrained('departments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
