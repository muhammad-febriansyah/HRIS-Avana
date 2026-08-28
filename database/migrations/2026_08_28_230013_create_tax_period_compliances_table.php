<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative follow-through on a tax period, which payroll itself never
 * records: the money was deposited to the state, and the return was filed.
 *
 * Payroll knows what was withheld; it cannot know whether anyone paid it in or
 * reported it. Those two acts happen in DJP's systems and come back as an NTPN
 * (deposit receipt) and an NTTE (filing receipt), so they are typed in here and
 * kept beside the period they settle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_period_compliances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();

            // pending | done — deliberately a string, not an enum: a tenant may
            // later need "sebagian" without an ALTER on a live table.
            $table->string('deposit_status', 16)->default('pending');
            $table->date('deposit_date')->nullable();
            $table->string('deposit_ntpn', 64)->nullable();

            $table->string('report_status', 16)->default('pending');
            $table->date('report_date')->nullable();
            $table->string('report_ntte', 64)->nullable();

            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One compliance record per period: the period IS the masa pajak.
            $table->unique(['tenant_id', 'payroll_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_period_compliances');
    }
};
