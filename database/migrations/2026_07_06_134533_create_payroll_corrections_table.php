<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Koreksi Gaji" (BPR manual 1.3.3): a manual pay adjustment for one employee —
 * an extra earning or deduction with a reason, approved by HR, then applied to
 * the payroll run whose period window contains the correction date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('correction_date');
            $table->string('type');                    // earning|deduction (pendapatan|potongan)
            $table->decimal('amount', 15, 2);
            $table->string('points')->nullable();      // poin perubahan
            $table->string('reason');                  // alasan perubahan
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending|approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_corrections');
    }
};
