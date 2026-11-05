<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Version numbers the mobile app compares itself against to offer (latest)
     * or require (minimum) an update from the store.
     */
    public function up(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('android_latest_version', 20)->nullable()->after('appstore_url');
            $table->string('android_min_version', 20)->nullable()->after('android_latest_version');
            $table->string('ios_latest_version', 20)->nullable()->after('android_min_version');
            $table->string('ios_min_version', 20)->nullable()->after('ios_latest_version');
            $table->text('app_update_notes')->nullable()->after('ios_min_version');
        });
    }

    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropColumn([
                'android_latest_version',
                'android_min_version',
                'ios_latest_version',
                'ios_min_version',
                'app_update_notes',
            ]);
        });
    }
};
