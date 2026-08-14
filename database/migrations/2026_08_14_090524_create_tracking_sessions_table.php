<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('attendance_id')->unique()->constrained('attendances')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->decimal('end_latitude', 10, 7)->nullable();
            $table->decimal('end_longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('total_distance_meters')->default(0);
            $table->unsignedBigInteger('total_duration_seconds')->default(0);
            $table->dateTime('last_location_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'last_location_at']);
            $table->index(['tenant_id', 'employee_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_sessions');
    }
};
