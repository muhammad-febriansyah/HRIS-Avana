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
            // Off by default, unlike the other portal tabs: this one lets a
            // partner flip feature modules on their own referred tenants —
            // a bigger trust grant than seeing leads or requesting a payout,
            // so a super admin has to opt in explicitly.
            $table->boolean('klien_tab_enabled')->default(false)->after('rekening_tab_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn('klien_tab_enabled');
        });
    }
};
