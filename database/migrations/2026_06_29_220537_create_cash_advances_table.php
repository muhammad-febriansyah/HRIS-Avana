<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('installments')->default(1);
            $table->decimal('monthly_deduction', 15, 2)->default(0);
            $table->date('request_date');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected|paid
            $table->foreignId('approved_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advances');
    }
};
