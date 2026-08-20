<?php

use App\Models\WebsiteSetting;
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
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->longText('account_deletion')->nullable()->after('terms_of_service');
        });

        WebsiteSetting::query()->whereKey(1)->update([
            'account_deletion' => WebsiteSetting::defaultAccountDeletionHtml(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->dropColumn('account_deletion');
        });
    }
};
