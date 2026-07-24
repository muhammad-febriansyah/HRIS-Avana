<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only accounting ledger for AI tokens. It is the source of truth for
 * monthly usage (free-quota and per-user cap sums) and wallet movements —
 * deliberately independent of `ai_messages`, whose rows cascade-delete with a
 * conversation and could otherwise let a user reset their usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // credit | debit
            $table->string('source'); // purchase | chat | adjustment
            $table->unsignedBigInteger('tokens'); // magnitude
            $table->bigInteger('wallet_delta')->default(0); // signed change to the wallet
            $table->unsignedBigInteger('balance_after')->nullable();
            $table->char('period', 7)->index(); // YYYY-MM
            $table->foreignId('ai_token_order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'period', 'type']);
            $table->index(['user_id', 'period', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_ledger');
    }
};
