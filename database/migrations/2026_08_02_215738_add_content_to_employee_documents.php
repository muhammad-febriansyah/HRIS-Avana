<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The readable text of an uploaded document.
 *
 * SOP files have been run through the extractor since they were introduced,
 * which is what lets the assistant quote them. Documents were only ever a file
 * on disk, so asking the assistant to summarise one had nothing to read.
 *
 * Null means not yet attempted; empty means attempted and nothing came back —
 * a scan, or a format the extractor cannot open. The difference matters when
 * telling someone why their document cannot be summarised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table): void {
            $table->longText('content')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table): void {
            $table->dropColumn('content');
        });
    }
};
