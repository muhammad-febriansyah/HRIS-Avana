<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_penalties', function (Blueprint $table): void {
            $table->foreignId('attendance_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('attendances')
                ->cascadeOnDelete();
            $table->string('source', 20)->default('manual')->after('violation_type')->index();
        });

        DB::table('attendance_penalties')
            ->where('notes', 'like', 'Otomatis dari absensi:%')
            ->orderBy('id')
            ->get()
            ->each(function (object $penalty): void {
                $attendanceId = DB::table('attendances')
                    ->where('tenant_id', $penalty->tenant_id)
                    ->where('employee_id', $penalty->employee_id)
                    ->where('date', $penalty->date)
                    ->where('status', $penalty->violation_type)
                    ->value('id');

                DB::table('attendance_penalties')
                    ->where('id', $penalty->id)
                    ->update([
                        'attendance_id' => $attendanceId,
                        'source' => 'automatic',
                    ]);
            });

        $duplicates = DB::table('attendance_penalties')
            ->select('tenant_id', 'attendance_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('attendance_id')
            ->groupBy('tenant_id', 'attendance_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $keep = DB::table('attendance_penalties')
                ->where('tenant_id', $duplicate->tenant_id)
                ->where('attendance_id', $duplicate->attendance_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('attendance_penalties')
                ->where('tenant_id', $duplicate->tenant_id)
                ->where('attendance_id', $duplicate->attendance_id)
                ->where('id', '!=', $keep)
                ->delete();
        }

        Schema::table('attendance_penalties', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'attendance_id'],
                'attendance_penalties_automatic_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_penalties', function (Blueprint $table): void {
            $table->dropUnique('attendance_penalties_automatic_unique');
            $table->dropConstrainedForeignId('attendance_id');
            $table->dropColumn('source');
        });
    }
};
