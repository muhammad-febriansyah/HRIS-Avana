<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_travels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->text('purpose')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('transport')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->decimal('per_diem', 15, 2)->default(0);
            $table->string('status')->default('pending'); // pending|approved|rejected|completed
            $table->foreignId('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_travels');
    }
};
