<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super-admin pricing catalogue: each pack sells a fixed number of AI tokens for
 * a Rupiah price. Tenants top up their permanent wallet by buying these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_token_packs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('token_amount');
            $table->unsignedBigInteger('price'); // Rupiah, integer (no cents)
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_token_packs');
    }
};
