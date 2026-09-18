<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roster_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_plan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 30);
            $table->string('category', 30)->nullable();
            $table->time('boundary_start')->nullable();
            $table->time('boundary_end')->nullable();
            $table->time('schedule_start')->nullable();
            $table->time('schedule_end')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['roster_plan_id', 'date'], 'roster_plan_days_plan_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_plan_days');
    }
};
