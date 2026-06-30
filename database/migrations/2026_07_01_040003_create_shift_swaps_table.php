<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_swaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('requester_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('target_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->timestamps();
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_swaps');
    }
};
