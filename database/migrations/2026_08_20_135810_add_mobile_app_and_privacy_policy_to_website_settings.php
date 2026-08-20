<?php

use App\Models\WebsiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('playstore_url')->nullable()->after('social_tiktok');
            $table->string('appstore_url')->nullable()->after('playstore_url');
            $table->longText('privacy_policy')->nullable()->after('contact_address');
        });

        // Seed the privacy policy with real starting content (not a blank
        // field) so the public page is never empty before a super admin
        // edits it for the first time.
        $settings = WebsiteSetting::current();

        if ($settings->privacy_policy === null) {
            $settings->update(['privacy_policy' => WebsiteSetting::defaultPrivacyPolicyHtml()]);
        }
    }

    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropColumn(['playstore_url', 'appstore_url', 'privacy_policy']);
        });
    }
};
