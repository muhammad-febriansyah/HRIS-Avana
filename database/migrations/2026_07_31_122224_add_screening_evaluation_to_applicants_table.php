<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Screening is a verdict somebody reached, not just a card that moved.
 *
 * The spec has HR review the CV, the experience and the skills, "memberikan
 * evaluasi", and only then decide Shortlisted or Rejected. Dragging a card
 * between columns recorded the move and lost the reasoning, which is exactly
 * what anyone asking "why was this candidate dropped?" needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->text('screening_note')->nullable()->after('stage');
            // 1–5. Coarse on purpose: a screener rates a CV, they do not score it.
            $table->unsignedTinyInteger('screening_score')->nullable()->after('screening_note');
            $table->foreignId('screened_by')->nullable()->after('screening_score')->constrained('users')->nullOnDelete();
            $table->timestamp('screened_at')->nullable()->after('screened_by');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('screened_by');
            $table->dropColumn(['screening_note', 'screening_score', 'screened_at']);
        });
    }
};
