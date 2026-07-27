<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index for the contributor leaderboard.
 *
 * It groups published posts by author within a tenant. The existing
 * `(tenant_id, status, id)` index cannot serve that grouping, so MySQL builds a
 * temporary table over the whole tenant's posts on every open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'status', 'employee_id'],
                'social_posts_leaderboard_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropIndex('social_posts_leaderboard_index');
        });
    }
};
