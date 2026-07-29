<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A personal AI token wallet, bought by the person who uses it.
 *
 * Until now every token belonged to the company: a monthly free quota plus a
 * shared wallet, rationed between people by a per-role cap. Somebody who ran out
 * had to wait for the admin. They can now buy their own, which sits behind the
 * company's pools and outside the cap — the cap rations what the company paid
 * for, and this is not that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_token_balance')->default(0)->after('token_version');
        });

        Schema::table('ai_token_orders', function (Blueprint $table): void {
            /**
             * Who the tokens land on once paid: the company wallet, or the
             * wallet of the person who ordered. `user_id` alone cannot say —
             * it already records who placed a company order.
             */
            $table->string('scope', 16)->default('tenant')->after('user_id');
        });

        Schema::table('ai_token_ledger', function (Blueprint $table): void {
            // A debit can draw on both wallets in one message, so the personal
            // movement is recorded beside the company one rather than instead.
            $table->integer('personal_delta')->default(0)->after('wallet_delta');
            $table->unsignedBigInteger('personal_after')->default(0)->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ai_token_balance');
        });

        Schema::table('ai_token_orders', function (Blueprint $table): void {
            $table->dropColumn('scope');
        });

        Schema::table('ai_token_ledger', function (Blueprint $table): void {
            $table->dropColumn(['personal_delta', 'personal_after']);
        });
    }
};
