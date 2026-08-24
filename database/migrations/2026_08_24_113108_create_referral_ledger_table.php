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
        // Append-only accounting entry, the same shape as `ai_token_ledger`:
        // a partner's balance is always SUM(points) here, never a column that
        // gets updated in place.
        Schema::create('referral_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->string('type'); // earn, void, withdraw
            $table->integer('points'); // signed
            $table->decimal('amount', 14, 2); // signed, rupiah
            $table->integer('balance_after'); // points balance after this entry
            $table->string('reference_type')->nullable(); // conversion, withdrawal
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_ledger');
    }
};
