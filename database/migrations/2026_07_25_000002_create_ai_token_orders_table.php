<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's purchase of an AI token pack, paid through Pakasir. `order_number`
 * is the id we hand the gateway; `amount` and `token_amount` are snapshots taken
 * at purchase so later price edits never change a pending order. The wallet is
 * credited exactly once, guarded by `credited_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_token_pack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pack_name');
            $table->unsignedBigInteger('token_amount');
            $table->unsignedBigInteger('amount'); // Rupiah snapshot — Pakasir verification key
            $table->string('status')->default('pending')->index();
            $table->string('payment_method')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_orders');
    }
};
