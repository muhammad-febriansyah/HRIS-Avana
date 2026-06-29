<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pph21_ter_rates', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->decimal('income_min', 15, 2)->default(0);
            $table->decimal('income_max', 15, 2)->nullable();
            $table->decimal('rate', 6, 4)->default(0);
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pph21_ter_rates');
    }
};
