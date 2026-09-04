<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagging: an author can tag colleagues on a post or a comment, Facebook-style.
 * Each tag fires an FCM push straight to the tagged employee — the one push in
 * the social wall that goes out even when the tagger and target never
 * otherwise interact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_post_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One tag per employee per post; retags are a no-op.
            $table->unique(['social_post_id', 'employee_id']);
        });

        Schema::create('social_post_comment_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['social_post_comment_id', 'employee_id'], 'social_comment_mentions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_comment_mentions');
        Schema::dropIfExists('social_post_mentions');
    }
};
