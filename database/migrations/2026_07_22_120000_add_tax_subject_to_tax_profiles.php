<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPh 21 subject category (PP 58/2023 & PMK 168/2023) drives which withholding
 * scheme applies: pegawai tetap → TER Bulanan; pegawai tidak tetap → TER Harian
 * or 50% × Pasal 17; bukan pegawai → 50% × Pasal 17; peserta / mantan pegawai →
 * Pasal 17. wage_basis + daily_wage support the daily-worker branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_profiles', function (Blueprint $table): void {
            $table->string('tax_subject')->default('pegawai_tetap')->after('employment_tax_type');
            $table->string('wage_basis')->default('monthly')->after('tax_subject'); // monthly|daily
            $table->decimal('daily_wage', 15, 2)->nullable()->after('wage_basis');
        });
    }

    public function down(): void
    {
        Schema::table('tax_profiles', function (Blueprint $table): void {
            $table->dropColumn(['tax_subject', 'wage_basis', 'daily_wage']);
        });
    }
};
