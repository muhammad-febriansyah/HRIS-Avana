<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partner_document_downloads', function (Blueprint $table) {
            // Null means the download came from a visitor who wasn't logged
            // in (the public partner page) — visitor_hash is all we have for
            // those. A logged-in mitra downloading from their portal gets a
            // real name here.
            $table->foreignId('user_id')->nullable()->after('visitor_hash')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_document_downloads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
