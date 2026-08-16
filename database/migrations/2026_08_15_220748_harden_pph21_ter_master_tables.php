<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pph21_ter_rates', function (Blueprint $table): void {
            $table->string('source_checksum', 64)->nullable()->after('source');
            $table->string('change_reason', 500)->nullable()->after('source_checksum');
            $table->foreignId('created_by')->nullable()->after('change_reason')->constrained('users')->nullOnDelete();
            $table->unique(['category', 'effective_start_date', 'income_min'], 'ter_rates_version_bracket_unique');
        });

        Schema::table('pph21_ter_categories', function (Blueprint $table): void {
            $table->string('source')->nullable()->after('effective_end_date');
            $table->string('source_checksum', 64)->nullable()->after('source');
            $table->string('change_reason', 500)->nullable()->after('source_checksum');
            $table->foreignId('created_by')->nullable()->after('change_reason')->constrained('users')->nullOnDelete();
            $table->unique(['ptkp_status', 'effective_start_date'], 'ter_categories_version_status_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pph21_ter_rates', function (Blueprint $table): void {
            $table->dropUnique('ter_rates_version_bracket_unique');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['source_checksum', 'change_reason']);
        });

        Schema::table('pph21_ter_categories', function (Blueprint $table): void {
            $table->dropUnique('ter_categories_version_status_unique');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['source', 'source_checksum', 'change_reason']);
        });
    }
};
