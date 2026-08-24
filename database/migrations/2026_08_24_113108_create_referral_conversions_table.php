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
        Schema::create('referral_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('base_amount', 14, 2); // the invoice total commission was computed from
            $table->unsignedInteger('points');
            $table->decimal('commission_amount', 14, 2); // points * point_value, snapshotted at credit time
            $table->string('mode'); // flat, percent — snapshot of referral_settings.mode at credit time
            $table->string('status')->default('pending'); // pending, approved, void
            $table->date('hold_until')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // One conversion per tenant: commission is earned on a client's
            // FIRST paid invoice only, never on renewals.
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_conversions');
    }
};
