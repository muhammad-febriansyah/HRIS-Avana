<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Nilai Komponen" (BPR manual 1.2.4): a component's nominal value mapped by
 * employee dimensions — jenis pegawai (kategori), jenis/status payroll,
 * posisi, pangkat/grade (job level) and klien/area (branch). The engine
 * resolves the most-specific matching row for each employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_component_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();

            // Mapping dimensions — each nullable; null = "applies to any".
            $table->string('kategori')->nullable();           // Organik / Non Organik BPO / Non Organik PE
            $table->string('employment_status')->nullable();  // PKWT / PKWTT / Magang ...
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('value', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'payroll_component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_component_values');
    }
};
