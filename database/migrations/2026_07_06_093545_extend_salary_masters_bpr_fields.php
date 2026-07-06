<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring the Master Gaji setting up to the BPR HRMS reference form: process type,
 * periode ranges (25 s.d 24) for gaji/overtime/absensi, day-count method,
 * overtime method (reguler/flat), kompensasi method and probation — plus the
 * per-component "included / kompensasi" flags to back the separate checklists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_masters', function (Blueprint $table) {
            $table->string('process_type')->nullable()->after('is_active');       // normal|bulan_lalu (Tipe Proses KISS)
            $table->unsignedTinyInteger('period_start_day')->nullable()->after('period_day'); // Periode Gaji dari
            $table->unsignedTinyInteger('period_end_day')->nullable()->after('period_start_day'); // s.d
            $table->string('day_calc_method')->nullable()->after('day_divisor');   // absen|hari_kerja|hari_kalender|formula
            $table->string('overtime_calc_method')->nullable()->after('day_calc_method'); // reguler|flat
            $table->unsignedTinyInteger('overtime_start_day')->nullable()->after('overtime_period');
            $table->unsignedTinyInteger('overtime_end_day')->nullable()->after('overtime_start_day');
            $table->unsignedTinyInteger('attendance_start_day')->nullable()->after('attendance_period');
            $table->unsignedTinyInteger('attendance_end_day')->nullable()->after('attendance_start_day');
            $table->string('compensation_method')->nullable()->after('attendance_end_day');
            $table->unsignedSmallInteger('probation_months')->nullable()->after('compensation_method');
        });

        Schema::table('salary_master_components', function (Blueprint $table) {
            $table->boolean('included')->default(true)->after('payroll_component_id'); // dicentang di Penerimaan/Potongan
            $table->boolean('is_kompensasi')->default(false)->after('is_overtime_base');
        });

        // Existing checklist rows are members, so mark them included.
        DB::table('salary_master_components')->update(['included' => true]);
    }

    public function down(): void
    {
        Schema::table('salary_masters', function (Blueprint $table) {
            $table->dropColumn([
                'process_type', 'period_start_day', 'period_end_day', 'day_calc_method',
                'overtime_calc_method', 'overtime_start_day', 'overtime_end_day',
                'attendance_start_day', 'attendance_end_day', 'compensation_method', 'probation_months',
            ]);
        });

        Schema::table('salary_master_components', function (Blueprint $table) {
            $table->dropColumn(['included', 'is_kompensasi']);
        });
    }
};
