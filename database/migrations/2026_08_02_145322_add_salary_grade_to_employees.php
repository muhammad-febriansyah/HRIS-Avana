<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attach an employee to a Struktur & Skala Upah grade.
 *
 * `salary_grades` has held min/mid/max per grade since 2026-07-01, but nothing
 * pointed at it — the table was a reference no record referenced, so the "cek
 * kewajaran nilai di Master Gaji" the documentation describes had no subject.
 * With a grade on the employee, a salary can be judged against both its band
 * and the branch UMR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('salary_grade_id')
                ->nullable()
                ->after('job_level_id')
                ->constrained('salary_grades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('salary_grade_id');
        });
    }
};
