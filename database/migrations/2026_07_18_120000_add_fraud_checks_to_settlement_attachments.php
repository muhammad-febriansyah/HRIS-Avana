<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tamper/authenticity screening to settlement supporting documents. Each
 * attachment gets a perceptual hash (for reuse detection), a 0-100 fraud risk
 * score, a risk level, the flags that fired, and the raw analysis (extracted
 * amount/date plus the vision model's findings). The settlement carries the
 * rolled-up worst level so Finance sees the risk before paying.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlement_attachments', function (Blueprint $table): void {
            $table->string('phash', 32)->nullable()->after('size');
            $table->unsignedTinyInteger('fraud_score')->nullable()->after('phash');
            $table->string('fraud_level')->nullable()->after('fraud_score'); // low|medium|high
            $table->json('fraud_flags')->nullable()->after('fraud_level');
            $table->json('fraud_analysis')->nullable()->after('fraud_flags');
            $table->timestamp('analyzed_at')->nullable()->after('fraud_analysis');

            $table->index('phash');
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->string('fraud_level')->nullable()->after('bank_swift'); // rollup: worst attachment level
            $table->timestamp('fraud_checked_at')->nullable()->after('fraud_level');
        });
    }

    public function down(): void
    {
        Schema::table('settlement_attachments', function (Blueprint $table): void {
            $table->dropIndex(['phash']);
            $table->dropColumn([
                'phash', 'fraud_score', 'fraud_level',
                'fraud_flags', 'fraud_analysis', 'analyzed_at',
            ]);
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropColumn(['fraud_level', 'fraud_checked_at']);
        });
    }
};
