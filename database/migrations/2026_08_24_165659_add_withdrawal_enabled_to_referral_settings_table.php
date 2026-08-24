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
            // Super admin kill switch for the mitra portal's withdrawal tab —
            // leads/komisi stay visible, only new withdrawal requests are blocked.
            $table->boolean('withdrawal_enabled')->default(true)->after('min_withdrawal_points');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn('withdrawal_enabled');
        });
    }
};
