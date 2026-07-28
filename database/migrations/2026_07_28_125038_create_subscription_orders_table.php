<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service subscription renewals: a tenant admin picks a package and a
 * duration, pays through Pakasir, and the paid order extends the subscription.
 *
 * `applied_at` is the idempotency guard — the browser callback and the webhook
 * both race to apply the same order, and only one may move the end date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('package_name');
            $table->string('billing_cycle');
            $table->unsignedSmallInteger('months');
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');
    }
};
