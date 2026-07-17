<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One receipt line backing a settlement — what was bought, when, for how much,
 * and the scan of the receipt proving it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->string('category')->default('operasional'); // mirrors reimbursement categories
            $table->string('description');
            $table->date('spent_date');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('receipt_path')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'settlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};
