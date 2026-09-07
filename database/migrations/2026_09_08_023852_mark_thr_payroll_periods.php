<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marks the THR periods already on file with the type the code now reads.
 *
 * Until now "is this THR?" was answered by matching the code against `THR-%` in
 * eleven separate queries. Generate THR is the only thing that writes that code,
 * so the backfill is exact: every existing THR period, and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_periods')
            ->where('code', 'like', 'THR-%')
            ->update(['type' => 'thr']);
    }

    public function down(): void
    {
        DB::table('payroll_periods')
            ->where('type', 'thr')
            ->update(['type' => 'bulanan']);
    }
};
