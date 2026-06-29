<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Superseded by the later create_employee_career_histories_table migration
        // (richer movement schema). Kept as a guarded no-op to avoid a duplicate
        // CREATE TABLE collision while preserving migration history.
        if (Schema::hasTable('employee_career_histories')) {
            return;
        }
    }

    public function down(): void
    {
        // No-op: the table is owned by the later career-histories migration.
    }
};
