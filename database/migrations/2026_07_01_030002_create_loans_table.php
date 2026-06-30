<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedInteger('tenor_months');
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('monthly_installment', 15, 2)->default(0);
            $table->text('purpose')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected|paid
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
