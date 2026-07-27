<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a reply is answering, when that is not obvious from the indent.
 *
 * Threads are one level deep, so replying to a reply lands beside it under the
 * same parent — which leaves a reader unable to tell whether it answers the
 * parent or the reply above. Naming the person restores that, the way the apps
 * this wall is modelled on do it.
 *
 * A column rather than text baked into the body: the name then follows the
 * employee if they are renamed, and the body stays the author's own words.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_post_comments', function (Blueprint $table): void {
            $table->foreignId('reply_to_employee_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_post_comments', function (Blueprint $table): void {
            $table->dropForeign(['reply_to_employee_id']);
            $table->dropColumn('reply_to_employee_id');
        });
    }
};
