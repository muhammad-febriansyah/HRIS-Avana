<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An explicit flag, not an inference from `package_id`/company presence:
     * "Tanpa Paket" is a legitimate, deliberate state for an admin-created
     * tenant (see TenantController::store()), so gating on package_id alone
     * would lock out every tenant that has always run that way. Only a
     * self-serve signup approved via ReferralController::approveTenant() is
     * ever created with this set — default false covers every tenant that
     * already existed before this gate shipped.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('requires_onboarding')->default(false)->after('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('requires_onboarding');
        });
    }
};
