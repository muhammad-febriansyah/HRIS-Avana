<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "UMR" (BPR payroll Komponen submenu): the regional minimum wage per area/year.
 * Tied to a branch (region) — a null branch is the tenant-wide default. Used as
 * the `umr` operand in Master Formula and as a "sesuai UMR" base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umr_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('region')->nullable();          // free-text label when no branch
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'year']);
            $table->index(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umr_rates');
    }
};
