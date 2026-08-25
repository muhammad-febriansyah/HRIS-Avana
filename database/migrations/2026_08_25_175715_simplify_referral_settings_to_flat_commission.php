<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops the poin abstraction: commission is now a single flat rupiah
     * amount per conversion, no percent-of-invoice mode. `flat_amount` and
     * `min_withdrawal_amount` are backfilled from the old points columns
     * before those are dropped, so the configured rate carries over.
     */
    public function up(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->decimal('flat_amount', 12, 2)->default(0)->after('mode'); // rupiah per conversion
            $table->decimal('min_withdrawal_amount', 12, 2)->default(0)->after('min_withdrawal_points');
        });

        DB::table('referral_settings')->update([
            'flat_amount' => DB::raw('points_per_conversion * point_value'),
            'min_withdrawal_amount' => DB::raw('min_withdrawal_points * point_value'),
        ]);

        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn(['mode', 'points_per_conversion', 'percent_rate', 'point_value', 'min_withdrawal_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->string('mode')->default('flat')->after('id');
            $table->unsignedInteger('points_per_conversion')->default(1);
            $table->decimal('percent_rate', 5, 2)->default(0);
            $table->decimal('point_value', 12, 2)->default(0);
            $table->unsignedInteger('min_withdrawal_points')->default(0);
        });

        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn(['flat_amount', 'min_withdrawal_amount']);
        });
    }
};
