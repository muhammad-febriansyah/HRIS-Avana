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
        Schema::create('tenant_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('phone');
            $table->string('admin_name');
            $table->string('admin_email');
            // The applicant's own chosen password, already hashed — carried
            // through to the real login TenantController::approve() creates
            // (see TenantProvisioner::createAdmin()), the same way
            // ReferralPartnerService::approve() does for a mitra login.
            $table->string('admin_password');
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('admin_note')->nullable(); // rejection reason
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_registrations');
    }
};
