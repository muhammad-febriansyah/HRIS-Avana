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
     */
    public function achievementPct(string $direction, ?float $target, ?float $actual): float
    {
        if ($target === null || $actual === null || $target <= 0.0) {
            return 0.0;
        }

        $ratio = $direction === 'lower_better'
            ? ($actual <= 0 ? 1.0 : $target / $actual)
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
     * Recompute a review's `manager_score` as the weight-weighted average of
     * its KPI items' achievement. No-ops when the review has no items, so the
     * legacy manual-entry path is left untouched.
     */
    public function recomputeManagerScore(PerformanceReview $review): void
    {
        $items = $review->kpiItems()->get(['weight', 'achievement_pct']);

        if ($items->isEmpty()) {
            return;
        }

        $totalWeight = (float) $items->sum('weight');
        $weighted = $totalWeight > 0
            ? $items->sum(fn (PerformanceKpiItem $item): float => (float) $item->weight * (float) $item->achievement_pct) / $totalWeight
            : 0.0;

        $review->update(['manager_score' => round(min(100.0, $weighted), 2)]);
    }

    /**
     * Called whenever a Key Result's progress changes: refreshes every KPI
     * item sourced from it and recomputes each affected review's score.
     */
    public function syncFromKeyResult(KeyResult $keyResult): void
    {
        $keyResult->performanceKpiItems()->get()->each(function (PerformanceKpiItem $item): void {
            $this->refreshItem($item);
            $this->recomputeManagerScore($item->review);
        });
    }
}
