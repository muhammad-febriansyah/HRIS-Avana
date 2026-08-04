<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which component a "Persentase" component takes its percentage of.
 *
 * Master Komponen has always offered Persentase as a Tipe Perhitungan, but it
 * stored only the percentage — never what the percentage was of — so the
 * payroll engine had nothing to multiply and paid the figure itself, turning
 * "Tunjangan Kinerja 10%" into Rp 10. A null column keeps the documented
 * default: a percentage of Gaji Pokok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->foreignId('percentage_of_component_id')
                ->nullable()
                ->after('basis_value')
                ->constrained('payroll_components')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('percentage_of_component_id');
        });
    }
};
