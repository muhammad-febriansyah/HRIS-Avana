<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tasklist an employee works through during a visit — each line ticked off
 * as it is done, which drives the visit's progress counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_visit_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_visit_id')->constrained('field_visits')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'field_visit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_visit_tasks');
    }
};
