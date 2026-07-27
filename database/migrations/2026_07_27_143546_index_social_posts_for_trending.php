<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index for the trending tab, which filters published posts of the last week
 * before ranking them. Without a date component MySQL scans the tenant's whole
 * wall to find that window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'status', 'created_at'],
                'social_posts_trending_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropIndex('social_posts_trending_index');
        });
    }
};
