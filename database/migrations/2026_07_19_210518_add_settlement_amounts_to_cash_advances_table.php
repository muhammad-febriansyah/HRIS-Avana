<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the cash advance loop. Until now the flow stopped at "Dicairkan":
 * money left the company and nothing recorded where it went. `settled_at`
 * existed but was never written by any code, so the "Belum Dipertanggungjawabkan"
 * counter read zero because the mechanism was absent, not because everything
 * was accounted for.
 *
 * An advance rarely costs exactly what was handed over, so both directions are
 * stored: what came back when the employee overdrew, and what the company still
 * owes them when they underdrew. Both are derived from `spent_amount` at
 * settlement time and kept, rather than recomputed later, so a change to the
 * original advance cannot rewrite a settled record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table): void {
            $table->decimal('spent_amount', 15, 2)->nullable()->after('settled_at');
            $table->decimal('returned_amount', 15, 2)->default(0)->after('spent_amount');
            $table->decimal('topup_amount', 15, 2)->default(0)->after('returned_amount');
            $table->string('settlement_receipt_path')->nullable()->after('topup_amount');
            $table->text('settlement_note')->nullable()->after('settlement_receipt_path');
            $table->foreignId('settled_by')->nullable()->after('settlement_note')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settled_by');
            $table->dropColumn([
                'spent_amount',
                'returned_amount',
                'topup_amount',
                'settlement_receipt_path',
                'settlement_note',
            ]);
        });
    }
};
