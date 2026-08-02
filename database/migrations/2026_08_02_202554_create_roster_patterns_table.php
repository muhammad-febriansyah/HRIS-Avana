<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotation patterns, so a roster is filled by the rule a company actually
 * works to rather than one cell at a time.
 *
 * A pattern is a cycle of steps read in order and repeated: "3 pagi, 3 siang,
 * 3 malam, 2 libur" is four steps. A step with no shift is time off, which is
 * part of the rotation rather than a gap in it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('roster_pattern_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_pattern_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            // Null is a day off — a real step in the cycle, not a missing shift.
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('days')->default(1);
            $table->timestamps();

            $table->index(['roster_pattern_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_pattern_steps');
        Schema::dropIfExists('roster_patterns');
    }
};
