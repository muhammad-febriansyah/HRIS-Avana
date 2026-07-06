<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Master Gaji" (BPR manual 1.2.1): a salary template — a named set of
 * components plus the period/cut-off/day settings — attached to employees so
 * payroll runs against a consistent structure per kategori gaji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_masters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('category');                          // kategori gaji
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);

            // Perhitungan settings (BPR manual "Setting Perhitungan Gaji").
            $table->unsignedTinyInteger('period_day')->nullable();   // tanggal periode gaji
            $table->unsignedTinyInteger('cut_off_day')->nullable();  // tanggal cut off
            $table->unsignedSmallInteger('day_divisor')->nullable(); // jumlah hari (faktor pembagi)
            $table->string('overtime_period')->nullable();           // berjalan|bulan_lalu
            $table->string('attendance_period')->nullable();         // berjalan|bulan_lalu
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_masters');
    }
};
