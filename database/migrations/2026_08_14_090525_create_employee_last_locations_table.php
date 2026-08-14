<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_last_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('tracking_session_id')->constrained('tracking_sessions')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2);
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_mocked')->default(false);
            $table->boolean('is_suspicious')->default(false);
            $table->dateTime('recorded_at', 3);
            $table->timestamps();
            $table->unique(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_last_locations');
    }
};
