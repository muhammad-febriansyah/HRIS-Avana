<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reshapes settlements from "cash-advance accountability" into a standalone
 * expense claim (Settlement Perdin): the employee lists expense line items, an
 * 11% tax is applied, a manager approves, then Finance verifies and pays the
 * total out to the employee's bank account. The old advance/return/topup model
 * is dropped.
 *
 * Existing settlement rows are cleared first — the two models are incompatible
 * and the prior rows were demo data tied to the removed advance concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Children first; both are demo-only rows tied to the removed model.
        DB::table('settlement_items')->delete();
        DB::table('settlements')->delete();

        Schema::table('settlements', function (Blueprint $table): void {
            // Drop the advance linkage and its snapshot/settlement columns.
            // FK first, then its backing unique index, then the column.
            $table->dropForeign(['cash_advance_id']);
            $table->dropUnique('settlements_cash_advance_id_unique');
            $table->dropColumn('cash_advance_id');
            $table->dropConstrainedForeignId('approver_id');
            $table->dropConstrainedForeignId('returned_received_by');
            $table->dropConstrainedForeignId('topup_paid_by');
            $table->dropColumn([
                'advance_amount',
                'total_spent',
                'approved_at',
                'returned_amount',
                'returned_at',
                'topup_amount',
                'topup_paid_at',
                'topup_method',
                'topup_reference',
            ]);

            // The claim's own header + money.
            $table->string('title')->after('number');
            $table->string('category')->nullable()->after('title');
            $table->string('department')->nullable()->after('category');
            $table->decimal('subtotal', 15, 2)->default(0)->after('department');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('subtotal');
            $table->decimal('total', 15, 2)->default(0)->after('tax_amount');

            // Payout account, snapshotted from the employee's primary account.
            $table->string('bank_name')->nullable()->after('total');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_holder')->nullable()->after('bank_account_number');
            $table->string('bank_swift')->nullable()->after('bank_account_holder');

            // Manager approval, then Finance verification + payout.
            $table->foreignId('manager_approved_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('manager_approved_at')->nullable()->after('manager_approved_by');
            $table->foreignId('finance_verified_by')->nullable()->after('manager_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('finance_verified_by');
            $table->string('payment_method')->nullable()->after('paid_at');
            $table->string('payment_reference')->nullable()->after('payment_method');
            $table->foreignId('rejected_by')->nullable()->after('payment_reference')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
        });

        // settlement_date carried the "accounted-for" date; it is now the claim
        // submission date. Renamed in its own statement so MySQL is happy.
        Schema::table('settlements', function (Blueprint $table): void {
            $table->renameColumn('settlement_date', 'submission_date');
        });

        // Line items no longer require a per-receipt date (the photo form omits it).
        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->date('spent_date')->nullable()->change();
        });

        Schema::create('settlement_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_id')->constrained('settlements')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'settlement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_attachments');

        Schema::table('settlement_items', function (Blueprint $table): void {
            $table->date('spent_date')->nullable(false)->change();
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->renameColumn('submission_date', 'settlement_date');
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manager_approved_by');
            $table->dropConstrainedForeignId('finance_verified_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'title', 'category', 'department', 'subtotal', 'tax_amount', 'total',
                'bank_name', 'bank_account_number', 'bank_account_holder', 'bank_swift',
                'manager_approved_at', 'paid_at', 'payment_method', 'payment_reference', 'rejected_at',
            ]);

            $table->foreignId('cash_advance_id')->nullable()->constrained('cash_advances')->cascadeOnDelete();
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('returned_amount', 15, 2)->default(0);
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('topup_amount', 15, 2)->default(0);
            $table->timestamp('topup_paid_at')->nullable();
            $table->foreignId('topup_paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topup_method')->nullable();
            $table->string('topup_reference')->nullable();
        });
    }
};
