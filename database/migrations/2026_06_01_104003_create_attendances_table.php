<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
            $table->date('date');
            $table->dateTime('clock_in_at')->nullable();
            $table->dateTime('clock_out_at')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('work_minutes')->default(0);
            $table->string('status')->default('present')->index();
            $table->string('location_status')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id', 'date']);
            $table->index(['tenant_id', 'branch_id', 'date']);
            $table->index(['tenant_id', 'date', 'status']);
            $table->unique(['tenant_id', 'employee_id', 'date', 'shift_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
