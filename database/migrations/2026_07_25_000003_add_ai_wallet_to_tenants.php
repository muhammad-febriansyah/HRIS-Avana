<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent AI token wallet on each tenant (topped up via Pakasir, never
 * expires) plus the default per-user monthly cap (null = unlimited).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_token_balance')->default(0)->after('ai_token_quota');
            $table->unsignedBigInteger('ai_token_user_cap')->nullable()->after('ai_token_balance');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['ai_token_balance', 'ai_token_user_cap']);
        });
    }
};
