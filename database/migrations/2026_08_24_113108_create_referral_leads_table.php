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
        Schema::create('referral_leads', function (Blueprint $table) {
            $table->id();
            // Nullable: the public inquiry form stays reachable without a
            // `?ref=` code, it just carries no commission then.
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            // Filled once a super admin converts this lead into a client.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone');
            $table->text('note')->nullable();
            $table->string('status')->default('new'); // new, contacted, converted, lost
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_leads');
    }
};
