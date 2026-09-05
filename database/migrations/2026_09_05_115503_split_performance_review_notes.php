<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gives the self-assessment and the calibration their own note columns.
 *
 * `performance_reviews.notes` had four different authors writing into it — the
 * appraisal note, the employee's self-assessment comment, the calibrator's
 * justification, and the reopen reason — each overwriting the last. The
 * practical damage was on the calibration form: it pre-filled the "reason for
 * adjusting the score" box with whatever the *employee* had written about
 * themselves, so an untouched form recorded the employee's own words as the
 * calibrator's justification — exactly the justification the deviation gate
 * exists to demand.
 *
 * Existing rows are deliberately left alone: which of the four authors wrote
 * the text now sitting in `notes` is no longer recoverable, so guessing would
 * only move the same ambiguity into a column that is supposed to be
 * unambiguous. `notes` keeps its historical content and, from here on, only the
 * appraisal note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('performance_reviews', 'self_notes')) {
                $table->text('self_notes')->nullable()->after('self_score');
            }

            if (! Schema::hasColumn('performance_reviews', 'calibration_notes')) {
                $table->text('calibration_notes')->nullable()->after('calibrated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropColumn(['self_notes', 'calibration_notes']);
        });
    }
};
