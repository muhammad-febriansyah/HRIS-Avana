<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_formula_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_formula_id')->constrained()->cascadeOnDelete();
            $table->string('tipe'); // penerimaan|potongan|umr
            $table->foreignId('payroll_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operator')->default('*'); // * or +
            $table->decimal('nilai', 15, 4)->default(0); // multiplier / value
            $table->boolean('prorate')->default(false);
            $table->string('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_formula_items');
    }
};
