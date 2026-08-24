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
        // Single settings row, same shape as `website_settings` / `ai_settings`:
        // one record the super admin edits in place.
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->id();
            // How a conversion's commission is computed: a fixed number of
            // points per conversion, or a percentage of the first invoice.
            // Either way `point_value` is the one rupiah-per-point exchange
            // rate withdrawals are always settled against.
            $table->string('mode')->default('flat'); // flat, percent
            $table->unsignedInteger('points_per_conversion')->default(1);
            $table->decimal('percent_rate', 5, 2)->default(0); // % of invoice total, percent mode
            $table->decimal('point_value', 12, 2)->default(0); // rupiah per point
            $table->unsignedInteger('hold_days')->default(14);
            $table->unsignedInteger('min_withdrawal_points')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_settings');
    }
};
