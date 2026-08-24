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
        Schema::table('referral_settings', function (Blueprint $table) {
            // Super admin's per-tab kill switches for the mitra portal sidebar.
            // Dashboard stays mandatory (it carries the referral link, the whole
            // point of the portal) — only these three plus the existing
            // `withdrawal_enabled` are togglable.
            $table->boolean('leads_tab_enabled')->default(true)->after('withdrawal_enabled');
            $table->boolean('komisi_tab_enabled')->default(true)->after('leads_tab_enabled');
            $table->boolean('rekening_tab_enabled')->default(true)->after('komisi_tab_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn(['leads_tab_enabled', 'komisi_tab_enabled', 'rekening_tab_enabled']);
        });
    }
};
