<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['position_id', 'payroll_component_id'], 'position_payroll_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_payroll_components');
    }
};
