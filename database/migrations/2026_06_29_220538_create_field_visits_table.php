<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->string('location');
            $table->string('client_name')->nullable();
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visits');
    }
};
