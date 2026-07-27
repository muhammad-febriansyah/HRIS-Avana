<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the assistant draw. Image generation uses a different model from chat, is
 * off until a super admin turns it on, and is billed out of the same token
 * wallet as chat — see `image_token_cost`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table): void {
            $table->boolean('image_enabled')->default(false)->after('is_enabled');
            $table->string('image_model')->nullable()->after('image_enabled');

            // Tokens charged per generated image. One ledger, one wallet: a
            // provider prices images per picture rather than per token, so the
            // price is converted here instead of becoming a second currency
            // that the meter, the per-user caps and the top-up packs would all
            // have to learn.
            //
            // The default is derived from what the platform itself sells:
            // Rp 150.000 buys 500.000 tokens (Rp 0,30 each), while one
            // 1024x1024 image costs roughly Rp 680 at the provider — about
            // 2.270 tokens. 2.500 covers that with a small margin.
            $table->unsignedInteger('image_token_cost')->default(2500)->after('image_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table): void {
            $table->dropColumn(['image_enabled', 'image_model', 'image_token_cost']);
        });
    }
};
