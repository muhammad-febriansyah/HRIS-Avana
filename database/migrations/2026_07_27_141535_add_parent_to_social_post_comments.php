<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Threaded replies under a comment.
 *
 * One level only: a reply to a reply attaches to the same top-level comment, so
 * a thread never marches off the right edge of a phone. That is what the social
 * apps this wall is modelled on do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_post_comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('social_post_id')
                ->constrained('social_post_comments')
                ->cascadeOnDelete();

            $table->index(['parent_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('social_post_comments', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'id']);
            $table->dropColumn('parent_id');
        });
    }
};
