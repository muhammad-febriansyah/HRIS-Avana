<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales Order (BPR manual 1.4): a client manpower request created in
     * Marketing that Payroll maps benefits onto — attaching a Master Gaji, a work
     * shift and a leave type — before it is forwarded to Recruitment.
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('client_name');
            $table->string('position_name');
            $table->unsignedInteger('headcount')->default(1);
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('status')->default('new'); // new|mapped
            $table->foreignId('salary_master_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
