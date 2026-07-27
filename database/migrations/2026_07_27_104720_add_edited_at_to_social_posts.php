<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a post was last edited.
 *
 * Inferring this from `updated_at > created_at` looked cheaper but is wrong:
 * the columns hold whole seconds, so a correction made moments after posting
 * reads as never edited — and any unrelated write to the row would mark a post
 * edited when it was not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
