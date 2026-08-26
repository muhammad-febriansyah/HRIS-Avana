<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('referral_settings', function (Blueprint $table): void {
            $table->renameColumn('flat_amount', 'percent_rate');
        });

        // A rupiah amount cannot be converted reliably without knowing each
        // invoice total; clear values outside the valid percentage range.
        DB::table('referral_settings')->where('percent_rate', '>', 100)->update(['percent_rate' => 0]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_settings', function (Blueprint $table): void {
            $table->renameColumn('percent_rate', 'flat_amount');
        });
    }
};
