<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category');
            $table->string('type')->default('internal'); // internal|external|online
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('instructor')->nullable();
            $table->unsignedInteger('quota')->nullable();
            $table->string('status')->default('planned'); // planned|ongoing|completed
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
