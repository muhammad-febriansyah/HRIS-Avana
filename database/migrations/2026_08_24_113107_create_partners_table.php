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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            // One referral profile per login. The account itself lives in
            // `users` (role `partner`) so a partner gets the same auth, 2FA
            // and audit trail as everyone else — this table only holds what
            // is specific to being a referral partner.
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('active'); // active, suspended
            $table->string('phone')->nullable();
            // Payout details the partner fills in themselves. Withdrawals
            // snapshot these at request time, so a later edit here never
            // rewrites a payout already in flight.
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('npwp')->nullable();
            // Per-partner override of the global commission rule. Null on
            // both = use `referral_settings`.
            $table->string('commission_mode')->nullable(); // flat, percent
            $table->decimal('commission_value', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
