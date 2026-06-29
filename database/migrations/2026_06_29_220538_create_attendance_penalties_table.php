<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_penalties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('violation_type'); // late|absent|incomplete|early_leave
            $table->string('penalty_type')->default('warning'); // warning|deduction
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_penalties');
    }
};
