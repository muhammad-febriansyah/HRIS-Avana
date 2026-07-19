<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A checklist row can now carry its own evidence: a before and an after shot
 * plus a caption. "Restocked the shelf" means little on its own — the pair of
 * photos is what makes it checkable, and the visit-level photo album cannot
 * express which task a picture belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_visit_tasks', function (Blueprint $table): void {
            $table->string('before_photo_path')->nullable()->after('done_at');
            $table->string('after_photo_path')->nullable()->after('before_photo_path');
            $table->string('photo_note')->nullable()->after('after_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('field_visit_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'before_photo_path',
                'after_photo_path',
                'photo_note',
            ]);
        });
    }
};
