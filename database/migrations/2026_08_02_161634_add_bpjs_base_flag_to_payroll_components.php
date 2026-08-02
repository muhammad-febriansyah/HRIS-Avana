<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a component say whether it counts toward the BPJS contribution base.
 *
 * The documented Master Komponen setup asks for two switches on every
 * component — "tandai apakah komponen ini kena PPh 21 dan/atau ikut basis
 * BPJS". Only the first existed (`is_taxable`); the contribution base was
 * whatever the employee's reported wage said, or the basic salary. So a
 * company whose BPJS base is Gaji Pokok + Tunjangan Jabatan had no way to say
 * so, and the premium came out on the wrong number.
 *
 * Existing rows are seeded to the basic wage alone, which is what the engine
 * already computed — nothing moves until someone ticks another component.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->boolean('is_bpjs_base')->default(false)->after('is_taxable');
        });

        DB::table('payroll_components')->where('code', 'BASIC')->update(['is_bpjs_base' => true]);
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table): void {
            $table->dropColumn('is_bpjs_base');
        });
    }
};
