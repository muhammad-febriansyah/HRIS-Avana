<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The components checked into a Master Gaji (BPR manual "Checklist Komponen
 * Penerimaan & Potongan") with the prorate and overtime-base flags the manual
 * captures per component (Gambar 45/46).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_master_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_master_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_prorate')->default(false);
            $table->boolean('is_overtime_base')->default(false);
            $table->timestamps();

            $table->unique(['salary_master_id', 'payroll_component_id'], 'smc_master_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_master_components');
    }
};
