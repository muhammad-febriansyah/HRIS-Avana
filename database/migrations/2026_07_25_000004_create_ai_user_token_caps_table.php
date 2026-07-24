<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user override of the monthly AI token cap. When absent the tenant default
 * (`tenants.ai_token_user_cap`) applies; a null `monthly_cap` means unlimited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_user_token_caps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_cap')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_user_token_caps');
    }
};
