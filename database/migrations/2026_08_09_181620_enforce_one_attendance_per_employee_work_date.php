<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('attendances')
            ->select('tenant_id', 'employee_id', 'date', DB::raw('COUNT(*) as total'))
            ->groupBy('tenant_id', 'employee_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $records = DB::table('attendances')
                ->where('tenant_id', $duplicate->tenant_id)
                ->where('employee_id', $duplicate->employee_id)
                ->where('date', $duplicate->date)
                ->orderByRaw('clock_in_at IS NULL')
                ->orderByRaw('clock_out_at IS NULL')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            $keep = $records->first();

            if ($keep === null) {
                continue;
            }

            foreach ($records->skip(1) as $record) {
                DB::table('attendance_selfies')
                    ->where('attendance_id', $record->id)
                    ->update(['attendance_id' => $keep->id]);

                DB::table('attendance_corrections')
                    ->where('attendance_id', $record->id)
                    ->update(['attendance_id' => $keep->id]);

                DB::table('attendances')->where('id', $record->id)->delete();
            }
        }

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropUnique('attendances_tenant_id_employee_id_date_shift_id_unique');
            $table->unique(
                ['tenant_id', 'employee_id', 'date'],
                'attendances_tenant_employee_work_date_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropUnique('attendances_tenant_employee_work_date_unique');
            $table->unique(['tenant_id', 'employee_id', 'date', 'shift_id']);
        });
    }
};
