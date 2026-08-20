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
            $table->longText('terms_of_service')->nullable()->after('privacy_policy');
        });

        $settings = WebsiteSetting::current();

        if ($settings->terms_of_service === null) {
            $settings->update(['terms_of_service' => WebsiteSetting::defaultTermsOfServiceHtml()]);
        }
    }

    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropColumn('terms_of_service');
        });
    }
};
