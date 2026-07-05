<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Drop leftover tables whose models/migrations were already removed. These
     * linger only in databases migrated before the removal; dropping them keeps
     * the schema to functioning tables only (Laravel built-ins excepted).
     */
    public function up(): void
    {
        foreach ([
            'activity_logs',
            'attachments',
            'cost_centers',
            'mobile_devices',
            'payslips',
            'pph21_calculation_results',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // One-way cleanup; the source tables were already removed with their models.
    }
};
