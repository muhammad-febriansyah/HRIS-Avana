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
        Schema::create('referral_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->decimal('amount', 14, 2);
            // Bank details are copied from the partner's profile at request
            // time — a later change to their profile must never rewrite a
            // payout that is already in flight or already settled.
            $table->string('bank_name');
            $table->string('bank_account_number');
            $table->string('bank_account_holder');
            $table->string('status')->default('pending'); // pending, approved, rejected, paid
            $table->string('proof_path')->nullable(); // transfer proof, private disk
            $table->text('note')->nullable(); // partner's note
            $table->text('admin_note')->nullable(); // rejection reason / payout note
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_withdrawals');
    }
};
