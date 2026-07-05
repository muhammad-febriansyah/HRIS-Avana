<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->string('component_group')->default('penerimaan')->after('type'); // penerimaan|potongan
            $table->boolean('show_on_slip')->default(true)->after('is_taxable');
            $table->string('period_basis')->nullable()->after('show_on_slip'); // berjalan|bulan_lalu
            $table->foreignId('payroll_formula_id')->nullable()->after('period_basis')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_formula_id');
            $table->dropColumn(['component_group', 'show_on_slip', 'period_basis']);
        });
    }
};
