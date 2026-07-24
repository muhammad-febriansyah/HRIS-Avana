<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user AI token caps do not scale (a tenant may have hundreds of users), so
 * caps are configured per ROLE instead. Replaces `ai_user_token_caps` with
 * `ai_role_token_caps`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_role_token_caps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('monthly_cap')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique('role_id');
        });

        Schema::dropIfExists('ai_user_token_caps');
    }

    public function down(): void
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

        Schema::dropIfExists('ai_role_token_caps');
    }
};
