<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `commission_amount` is already the rupiah source of truth — `points`
     * was only a display unit — and `mode` is meaningless now that commission
     * is always flat.
     */
    public function up(): void
    {
        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->dropColumn(['points', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->unsignedInteger('points')->default(0)->after('base_amount');
            $table->string('mode')->default('flat')->after('commission_amount');
        });
    }
};
