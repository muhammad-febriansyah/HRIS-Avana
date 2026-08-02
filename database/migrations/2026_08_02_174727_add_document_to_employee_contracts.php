<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a contract carry its signed document.
 *
 * The Kontrak screen recorded a contract's terms but had nowhere to keep the
 * contract itself, so the signed PDF lived in someone's email. The file goes
 * on the private disk and is served through a gated route — an employment
 * contract carries salary and personal data and has no business sitting on a
 * publicly reachable URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table): void {
            $table->string('document_path')->nullable()->after('notes');
            $table->string('document_name')->nullable()->after('document_path');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contracts', function (Blueprint $table): void {
            $table->dropColumn(['document_path', 'document_name', 'document_size']);
        });
    }
};
