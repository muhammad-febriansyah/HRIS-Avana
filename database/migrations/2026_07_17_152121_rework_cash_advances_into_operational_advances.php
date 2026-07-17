<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cash advance is an operational float paid out up front and accounted for
 * afterwards with receipts (see settlements) — not a salary-deducted loan. The
 * instalment columns and the payroll hook they fed are therefore dropped;
 * salary-deducted lending stays available through the separate `loans` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table): void {
            $table->dropColumn(['installments', 'monthly_deduction', 'paid_installments']);
        });

        Schema::table('cash_advances', function (Blueprint $table): void {
            $table->string('purpose')->nullable()->after('amount');
            $table->date('needed_date')->nullable()->after('request_date');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('disbursed_at')->nullable()->after('approved_at');
            $table->foreignId('disbursed_by')->nullable()->after('disbursed_at')->constrained('users')->nullOnDelete();
            $table->string('disbursement_method')->nullable()->after('disbursed_by');
            $table->string('disbursement_reference')->nullable()->after('disbursement_method');
            $table->timestamp('settled_at')->nullable()->after('disbursement_reference');
        });

        // 'paid' meant "instalments finished" under the old lending model; the
        // closest state in the new flow is an advance already accounted for.
        DB::table('cash_advances')->where('status', 'paid')->update(['status' => 'settled']);
    }

    public function down(): void
    {
        DB::table('cash_advances')
            ->whereIn('status', ['settled', 'disbursed'])
            ->update(['status' => 'paid']);

        Schema::table('cash_advances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('disbursed_by');
            $table->dropColumn([
                'purpose',
                'needed_date',
                'approved_at',
                'disbursed_at',
                'disbursement_method',
                'disbursement_reference',
                'settled_at',
            ]);
        });

        Schema::table('cash_advances', function (Blueprint $table): void {
            $table->unsignedInteger('installments')->default(1)->after('amount');
            $table->decimal('monthly_deduction', 15, 2)->default(0)->after('installments');
            $table->unsignedInteger('paid_installments')->default(0)->after('monthly_deduction');
        });
    }
};
