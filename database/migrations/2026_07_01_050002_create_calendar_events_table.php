<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('event'); // holiday|meeting|training|event|deadline
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('all_day')->default(true);
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
