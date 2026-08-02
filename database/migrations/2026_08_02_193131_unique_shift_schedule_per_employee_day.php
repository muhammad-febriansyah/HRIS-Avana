<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One roster row per employee per day, enforced by the database.
 *
 * Every writer already checks for an existing row before inserting, but the
 * check and the insert are two statements: the web roster and the phone can
 * both pass it at the same time and both insert. A second row is not a
 * cosmetic problem — attendance resolves the day's shift with `first()`, so
 * which shift someone is judged against would come down to row order.
 *
 * Duplicates are collapsed first, keeping the most recently updated row, which
 * is the one whoever edited last intended.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('shift_schedules')
            ->select('employee_id', 'date', DB::raw('COUNT(*) as total'))
            ->groupBy('employee_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keep = DB::table('shift_schedules')
                ->where('employee_id', $duplicate->employee_id)
                ->where('date', $duplicate->date)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('shift_schedules')
                ->where('employee_id', $duplicate->employee_id)
                ->where('date', $duplicate->date)
                ->where('id', '!=', $keep)
                ->delete();
        }

        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'employee_id', 'date'], 'shift_schedules_employee_day_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shift_schedules', function (Blueprint $table): void {
            $table->dropUnique('shift_schedules_employee_day_unique');
        });
    }
};
