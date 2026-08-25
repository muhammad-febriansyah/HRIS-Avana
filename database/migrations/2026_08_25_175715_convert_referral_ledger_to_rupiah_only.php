<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `balance_after` was a points running-balance; nothing in the app reads
     * it (the real balance is always `SUM(amount)`), so it's recomputed here
     * as a rupiah running balance purely for the ledger to stay a readable
     * audit trail. `points` is dropped — `amount` was always the real money.
     */
    public function up(): void
    {
        $running = [];
        $balanceAfter = [];

        DB::table('referral_ledger')->orderBy('partner_id')->orderBy('id')->get(['id', 'partner_id', 'amount'])
            ->each(function (object $row) use (&$running, &$balanceAfter): void {
                $running[$row->partner_id] = ($running[$row->partner_id] ?? 0) + (float) $row->amount;
                $balanceAfter[$row->id] = $running[$row->partner_id];
            });

        Schema::table('referral_ledger', function (Blueprint $table) {
            $table->dropColumn(['points', 'balance_after']);
        });

        Schema::table('referral_ledger', function (Blueprint $table) {
            $table->decimal('balance_after', 14, 2)->default(0)->after('amount');
        });

        foreach ($balanceAfter as $id => $value) {
            DB::table('referral_ledger')->where('id', $id)->update(['balance_after' => $value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referral_ledger', function (Blueprint $table) {
            $table->dropColumn('balance_after');
        });

        Schema::table('referral_ledger', function (Blueprint $table) {
            $table->integer('points')->default(0)->after('type');
            $table->integer('balance_after')->default(0)->after('amount');
        });
    }
};
