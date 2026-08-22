<?php

namespace App\Services;

use App\Models\KeyResult;
use App\Models\PerformanceKpiItem;
use App\Models\PerformanceReview;

/**
 * Computes KPI item achievement and rolls it up into a review's
 * `manager_score` — the real "KPI end-to-end" math the audit found missing.
 *
 * A review with zero KPI items is left in legacy/manual mode: nothing here
 * touches its `manager_score`, so a manager typing it in directly (as before
 * this feature existed) still works.
 */
final class PerformanceKpiScorer
{
    /**
     * Achievement percentage for a single manual KPI item.
     *
     * `higher_better`: actual/target. `lower_better`: target/actual (so
     * spending less than target still scores well). Clamped to [0, 200] so
     * one indicator overshooting can't be typed in as an absurd number, while
     * still letting genuine over-achievement count for more than 100%.
     *
     * For `lower_better`, an actual of zero is the best possible outcome, so it
     * takes the 200% ceiling — the same value the ratio tends towards as the
     * actual approaches zero. Treating it as 100% instead would make the curve
     * jump backwards at its own optimum.
     */
    public function achievementPct(string $direction, ?float $target, ?float $actual): float
    {
        if ($target === null || $actual === null || $target <= 0.0 || $actual < 0.0) {
            return 0.0;
        }

        $ratio = $direction === 'lower_better'
            ? ($actual <= 0.0 ? 2.0 : $target / $actual)
            : $actual / $target;

        return round(min(200.0, max(0.0, $ratio * 100)), 2);
    }

    /**
     * Refresh a single item's achievement. For a `key_result`-sourced item
     * this pulls live from the linked KeyResult's progress instead of
     * computing from target/actual (those fields are read-only for this
     * source).
     */
    public function refreshItem(PerformanceKpiItem $item): void
    {
        if ($item->source === 'key_result' && $item->keyResult !== null) {
            $item->update(['achievement_pct' => min(100.0, (float) $item->keyResult->progress)]);

            return;
        }

        $item->update([
            'achievement_pct' => $this->achievementPct($item->direction, $item->target_value !== null ? (float) $item->target_value : null, $item->actual_value !== null ? (float) $item->actual_value : null),
        ]);
    }

    /**
     * Recompute a review's `manager_score` from its KPI items' achievement,
     * against the full 100% weight budget — *not* against whatever weight
     * happens to be assigned so far.
     *
     * Normalising by the assigned weight would let a single 10%-weight item at
     * 100% achievement read as a manager score of 100 on an otherwise empty
     * scorecard. Dividing by the fixed budget instead means a half-built
     * scorecard scores half, and the calibration gate
     * ({@see PerformanceReviewWorkflow::assertKpiComplete()}) refuses to
     * finalize anything that doesn't total exactly 100%.
     *
     * No-ops for a manual review, so the manual-entry path is left untouched.
     * For a KPI review whose last item was just deleted, the score is cleared
     * rather than left standing: a scorecard with nothing on it must not keep
     * paying out the number its deleted items used to produce.
     */
    public function recomputeManagerScore(PerformanceReview $review): void
    {
        if ($review->scoring_mode !== 'kpi') {
            return;
        }

        $items = $review->kpiItems()->get(['weight', 'achievement_pct']);

        if ($items->isEmpty()) {
            $review->update(['manager_score' => null]);

            return;
        }

        $weighted = $items->sum(
            fn (PerformanceKpiItem $item): float => (float) $item->weight * (float) $item->achievement_pct
        ) / 100.0;

        $review->update(['manager_score' => round(min(100.0, max(0.0, $weighted)), 2)]);
    }

    /**
     * Called whenever a Key Result's progress changes: refreshes every KPI
     * item sourced from it and recomputes each affected review's score.
     *
     * Finalized reviews are skipped — a completed appraisal is a signed record,
     * and letting later OKR edits rewrite its score would silently change a
     * rating that has already been calibrated and consumed downstream.
     */
    public function syncFromKeyResult(KeyResult $keyResult): void
    {
        $keyResult->performanceKpiItems()->with('review')->get()->each(function (PerformanceKpiItem $item): void {
            if ($item->review === null || $item->review->status === 'completed') {
                return;
            }

            $this->refreshItem($item);
            $this->recomputeManagerScore($item->review);
        });
    }
}
