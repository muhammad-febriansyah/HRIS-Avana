<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The employee social wall: categorised posts with likes and comments.
 *
 * Posts publish immediately — there is no approval queue. HR moderates after
 * the fact by hiding or deleting, and every post carries its author (no
 * anonymous posting), so the wall stays accountable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Master categories, e.g. Ide Perbaikan, Sports Day, Employee of the Month.
        Schema::create('social_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            // Lucide icon name + hex accent, so a category reads as a chip.
            $table->string('icon')->default('sparkles');
            $table->string('color')->default('#2F54C9');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('social_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('image_path')->nullable();
            // Counter caches: a feed page would otherwise run two aggregates per
            // row. Kept in step by SocialPost's like/comment writers.
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            // `hidden` = taken down by HR but kept for audit.
            $table->string('status')->default('published');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'id']);
            $table->index(['tenant_id', 'social_category_id']);
        });

        Schema::create('social_post_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One like per employee per post; the toggle relies on this.
            $table->unique(['social_post_id', 'employee_id']);
        });

        Schema::create('social_post_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['social_post_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_post_comments');
        Schema::dropIfExists('social_post_likes');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_categories');
    }
};
