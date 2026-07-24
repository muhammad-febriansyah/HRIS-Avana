<?php

use App\Support\AvanaNav;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level appearance theme (for the super admin's own chrome), stored on
 * the website_settings singleton, plus the "Tampilan & Tema" leaf in the
 * platform navigation. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->json('theme')->nullable()->after('contact_address');
        });

        AvanaNav::seedPlatformDefaults();
    }

    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->dropColumn('theme');
        });
    }
};
