<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Functional "Setup Master" config: smart alerts, notification routing, scan
 * frequency and per-factor enable/disable — all driving the attrition scan
 * command, not just display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attrition_settings', function (Blueprint $table): void {
            $table->boolean('alerts_enabled')->default(true)->after('band_medium');
            $table->unsignedTinyInteger('alert_threshold')->default(75)->after('alerts_enabled');
            $table->boolean('weekly_summary')->default(false)->after('alert_threshold');
            // Role code that receives the alert for each risk band, e.g.
            // {"high":"admin_tenant_hr","medium":"manager","low":null}.
            $table->json('notify_roles')->nullable()->after('weekly_summary');
            $table->string('scan_frequency')->default('daily')->after('notify_roles'); // daily|weekly|off
            // Factor keys turned off in the Indicators panel; skipped by the scorer.
            $table->json('disabled_factors')->nullable()->after('scan_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('attrition_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'alerts_enabled', 'alert_threshold', 'weekly_summary',
                'notify_roles', 'scan_frequency', 'disabled_factors',
            ]);
        });
    }
};
