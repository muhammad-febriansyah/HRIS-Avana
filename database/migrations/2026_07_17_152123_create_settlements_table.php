<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts for one disbursed cash advance. `advance_amount` is snapshotted at
 * submission so later edits to the advance cannot silently move the balance,
 * and `total_spent` is the sum of the settlement's items.
 *
 * The balance (advance_amount - total_spent) decides what closes it:
 *   positive — the employee returns the leftover (`returned_amount`);
 *   negative — the company pays the employee the shortfall (`topup_amount`);
 *   zero     — nothing moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_advance_id')->constrained('cash_advances')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->date('settlement_date');
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->string('status')->default('draft'); // draft|submitted|approved|rejected|closed
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Leftover handed back by the employee.
            $table->decimal('returned_amount', 15, 2)->default(0);
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_received_by')->nullable()->constrained('users')->nullOnDelete();

            // Shortfall paid out to the employee.
            $table->decimal('topup_amount', 15, 2)->default(0);
            $table->timestamp('topup_paid_at')->nullable();
            $table->foreignId('topup_paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topup_method')->nullable();
            $table->string('topup_reference')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // One settlement per advance — an advance is accounted for once.
            $table->unique('cash_advance_id');
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
