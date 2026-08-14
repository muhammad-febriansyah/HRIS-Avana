<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_session_id')->constrained('tracking_sessions')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->uuid('client_uuid');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2);
            $table->decimal('altitude', 10, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->boolean('is_mocked')->default(false);
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->boolean('is_accepted')->default(true);
            $table->string('suspicion_reason', 64)->nullable();
            $table->unsignedInteger('distance_meters')->default(0);
            $table->dateTime('recorded_at', 3);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['tracking_session_id', 'client_uuid']);
            $table->index(['tracking_session_id', 'recorded_at']);
            $table->index(['tenant_id', 'employee_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_locations');
    }
};
