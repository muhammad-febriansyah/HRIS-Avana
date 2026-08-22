<?php

namespace App\Services;

use App\Models\PerformanceKpiItem;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewRevision;
use App\Models\User;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the performance review lifecycle as a real state machine:
 * pending → self_review → manager_review → calibration → completed.
 *
 * Every mutation that changes a review's status or finalizes its score goes
 * through here instead of the controller writing `status`/`final_score`
 * directly, so a review can no longer skip self-assessment, manager scoring,
 * or calibration on the way to `completed`.
 *
 * Each transition runs inside a transaction against a row-locked, freshly read
 * copy of the review, so two concurrent requests can no longer both observe the
 * same pre-transition status and both apply their write.
 *
 * Reviews created before this service existed are quarantined as `is_legacy`
 * rather than grandfathered silently: they keep their historical status but are
 * excluded from {@see PerformanceReview::scopePublishable()}.
 */
final class PerformanceReviewWorkflow
{
    /**
     * Allowed forward transitions, keyed by current status.
     *
     * `pending` may go straight to `manager_review` because submitting the
     * self-assessment *is* the event that ends the `self_review` stage — there
     * is no separate "self-review completed, still self_review" sub-state to
     * pass through first.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'pending' => ['self_review', 'manager_review'],
        'self_review' => ['manager_review'],
        'manager_review' => ['calibration'],
        'calibration' => ['completed'],
        'completed' => [],
    ];

    /**
     * Statuses `reopen()` is allowed to send a completed review back to.
     *
     * @var array<int, string>
     */
    private const REOPEN_TARGETS = ['manager_review', 'calibration'];

    /**
     * How far a calibrated score may move away from the manager's score before
     * the calibrator has to write down why.
     */
    private const CALIBRATION_DEVIATION_TOLERANCE = 10.0;

    /**
     * Tolerance for floating-point comparisons against the 100% weight budget.
     */
    private const WEIGHT_EPSILON = 0.01;

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Abort with 422 unless the review may move from its current status to
     * `$to` under the transition map above.
     */
    public function assertTransition(PerformanceReview $review, string $to): void
    {
        abort_unless(
            $this->canTransition($review->status, $to),
            422,
            "Penilaian tidak dapat berpindah dari status '{$review->status}' ke '{$to}'."
        );
    }

    /**
     * Abort with 422 unless the review's cycle is currently active. Draft and
     * closed cycles are read-only — nothing in the review lifecycle may move
     * while the cycle isn't open.
     */
    public function assertCycleOpen(PerformanceReview $review): void
    {
        abort_unless(
            $review->cycle?->status === 'active',
            422,
            'Siklus penilaian ini tidak aktif.'
        );
    }

    /**
     * Abort with 423 (Locked) if the review has already been finalized.
     * Completed reviews are read-only except through {@see reopen()}.
     */
    public function assertMutable(PerformanceReview $review): void
    {
        abort_if(
            $review->status === 'completed',
            423,
            'Penilaian yang sudah selesai terkunci. Buka kembali untuk mengubah.'
        );
    }

    /**
     * Abort with 422 unless the review's KPI items form a complete scorecard:
     * weights totalling exactly 100% and every manual item carrying an actual.
     *
     * A review with no KPI items is in legacy/manual mode and is checked by the
     * caller instead (it must then carry a manually entered manager score).
     */
    public function assertKpiComplete(PerformanceReview $review): void
    {
        if ($review->scoring_mode !== 'kpi') {
            return;
        }

        $items = $review->kpiItems()->get();

        // A review committed to KPI scoring whose items were all removed has no
        // basis for a score at all — it must either get its scorecard back or
        // be switched to manual scoring.
        abort_if(
            $items->isEmpty(),
            422,
            'Penilaian ini memakai skema KPI tetapi tidak punya item KPI.'
        );

        $totalWeight = (float) $items->sum(fn (PerformanceKpiItem $item): float => (float) $item->weight);

        abort_unless(
            abs($totalWeight - 100.0) <= self::WEIGHT_EPSILON,
            422,
            'Total bobot item KPI harus tepat 100%. Saat ini '.round($totalWeight, 2).'%.'
        );

        $missingActual = $items->first(
            fn (PerformanceKpiItem $item): bool => $item->source === 'manual' && $item->actual_value === null
        );

        abort_if(
            $missingActual !== null,
            422,
            "Realisasi item KPI '{$missingActual?->label}' belum diisi."
        );

        $orphanKeyResult = $items->first(
            fn (PerformanceKpiItem $item): bool => $item->source === 'key_result' && $item->key_result_id === null
        );

        abort_if(
            $orphanKeyResult !== null,
            422,
            "Item KPI '{$orphanKeyResult?->label}' kehilangan Key Result sumbernya."
        );
    }

    /**
     * Record the employee's self-assessment and hand the review to their
     * manager: pending|self_review → manager_review.
     */
    public function submitSelfAssessment(PerformanceReview $review, float $selfScore, ?string $notes): void
    {
        $this->transition($review, 'manager_review', function (PerformanceReview $locked) use ($selfScore, $notes): array {
            return [
                'self_score' => $selfScore,
                'notes' => $notes ?? $locked->notes,
                'status' => 'manager_review',
            ];
        });
    }

    /**
     * Record the manager's score and move the review into calibration:
     * manager_review → calibration.
     *
     * `$managerScore` is only written when the review has no KPI items yet
     * (legacy/manual mode); once KPI items exist, {@see PerformanceKpiScorer}
     * is the sole writer of `manager_score` and this value is ignored.
     *
     * Either way the review may not leave manager review as an empty click: it
     * must end up with a manager score, backed by either a complete KPI
     * scorecard or a manually entered number.
     */
    public function submitManagerScore(PerformanceReview $review, ?float $managerScore, ?string $reviewDate, User $actor): void
    {
        $this->transition($review, 'calibration', function (PerformanceReview $locked) use ($managerScore, $reviewDate, $actor): array {
            $usesKpi = $locked->scoring_mode === 'kpi';

            $this->assertKpiComplete($locked);

            $resolvedScore = $usesKpi
                ? ($locked->manager_score !== null ? (float) $locked->manager_score : null)
                : ($managerScore ?? ($locked->manager_score !== null ? (float) $locked->manager_score : null));

            abort_if(
                $resolvedScore === null,
                422,
                'Nilai atasan wajib diisi, atau lengkapi item KPI penilaian ini terlebih dahulu.'
            );

            // The review date decides which incentive period this rating is
            // paid in, so it cannot be left to chance and cannot fall outside
            // the period being appraised.
            $resolvedDate = $reviewDate ?? $locked->review_date?->toDateString();

            abort_if($resolvedDate === null, 422, 'Tanggal penilaian wajib diisi.');
            $this->assertReviewDateWithinCycle($locked, $resolvedDate);

            $data = [
                'status' => 'calibration',
                'review_date' => $resolvedDate,
                // Recorded so calibration can refuse the same person signing
                // both the manager score and the calibrated score.
                'manager_scored_by' => $actor->id,
                'manager_scored_at' => Carbon::now(),
            ];

            if (! $usesKpi) {
                $data['manager_score'] = $resolvedScore;
            }

            return $data;
        });
    }

    /**
     * Abort with 422 when the review date falls outside its cycle's period.
     * A December rating filed against a Q1 cycle silently lands in the wrong
     * incentive period, or in none at all.
     */
    public function assertReviewDateWithinCycle(PerformanceReview $review, string $reviewDate): void
    {
        $cycle = $review->cycle;

        if ($cycle?->period_start === null || $cycle->period_end === null) {
            return;
        }

        $date = Carbon::parse($reviewDate)->startOfDay();

        abort_unless(
            $date->betweenIncluded($cycle->period_start->startOfDay(), $cycle->period_end->endOfDay()),
            422,
            'Tanggal penilaian harus berada dalam periode siklus ('
            .$cycle->period_start->toDateString().' s/d '.$cycle->period_end->toDateString().').'
        );
    }

    /**
     * Finalize a review: calibration → completed. This is the only path that
     * may set `final_score`/`status=completed`.
     *
     * A calibrated score that departs from the manager's score by more than
     * {@see self::CALIBRATION_DEVIATION_TOLERANCE} points requires a written
     * justification — that is the whole point of the calibration gate.
     */
    public function calibrate(PerformanceReview $review, float $calibratedScore, User $actor, ?string $notes): void
    {
        $this->transition($review, 'completed', function (PerformanceReview $locked) use ($calibratedScore, $actor, $notes): array {
            $this->assertKpiComplete($locked);

            abort_if(
                $locked->manager_score === null,
                422,
                'Penilaian atasan belum ada — tidak dapat dikalibrasi.'
            );

            // Four eyes: whoever entered the manager score cannot also be the
            // one who signs it off, no matter what permissions they hold.
            abort_if(
                $locked->manager_scored_by !== null && (int) $locked->manager_scored_by === (int) $actor->id,
                403,
                'Kalibrasi harus dilakukan oleh pihak lain, bukan penilai yang mengisi nilai atasan.'
            );

            abort_if(
                abs($calibratedScore - (float) $locked->manager_score) > self::CALIBRATION_DEVIATION_TOLERANCE
                    && trim((string) $notes) === '',
                422,
                'Alasan kalibrasi wajib diisi bila nilai berbeda lebih dari '.self::CALIBRATION_DEVIATION_TOLERANCE.' poin dari nilai atasan.'
            );

            return [
                'calibrated_score' => $calibratedScore,
                'final_score' => $calibratedScore,
                'calibrated_by' => $actor->id,
                'calibrated_at' => Carbon::now(),
                'notes' => $notes ?? $locked->notes,
                'status' => 'completed',
                'is_legacy' => false,
            ];
        });
    }

    /**
     * Reopen a completed review, sending it back to an earlier stage for
     * correction. This deliberately bypasses the forward-only transition map —
     * it is the one sanctioned backward move, gated by the caller's permission
     * check and always attributed via the reason appended to `notes`.
     *
     * The superseded scores are snapshotted into a
     * {@see PerformanceReviewRevision} and then cleared from the live row, so a
     * review under correction immediately stops being publishable downstream
     * instead of continuing to pay out its old rating.
     */
    public function reopen(PerformanceReview $review, string $to, User $actor, string $reason): void
    {
        abort_unless(in_array($to, self::REOPEN_TARGETS, true), 422);

        DB::transaction(function () use ($review, $to, $actor, $reason): void {
            $locked = $this->lock($review);

            abort_unless($locked->status === 'completed', 422, 'Hanya penilaian selesai yang dapat dibuka kembali.');
            $this->assertCycleOpen($locked);

            PerformanceReviewRevision::create([
                'tenant_id' => $locked->tenant_id,
                'review_id' => $locked->id,
                // Denormalised so the revision still identifies its subject if
                // the review row is later deleted outright.
                'employee_id' => $locked->employee_id,
                'cycle_id' => $locked->cycle_id,
                'reopened_by' => $actor->id,
                'from_status' => $locked->status,
                'to_status' => $to,
                'self_score' => $locked->self_score,
                'manager_score' => $locked->manager_score,
                'final_score' => $locked->final_score,
                'calibrated_score' => $locked->calibrated_score,
                'calibrated_by' => $locked->calibrated_by,
                'calibrated_at' => $locked->calibrated_at,
                'reason' => $reason,
            ]);

            $changes = [
                'status' => $to,
                'final_score' => null,
                'calibrated_score' => null,
                'calibrated_by' => null,
                'calibrated_at' => null,
                'is_legacy' => false,
                'notes' => trim(($locked->notes ?? '')."\n[Dibuka kembali oleh {$actor->name}] {$reason}"),
            ];

            // Sending a review back to manager review means the manager's
            // judgement is what's being redone. Leaving the old score in place
            // would let it walk straight back to calibration on one click,
            // still carrying the number that was just rejected.
            if ($to === 'manager_review') {
                $changes['manager_scored_by'] = null;
                $changes['manager_scored_at'] = null;

                if ($locked->scoring_mode !== 'kpi') {
                    $changes['manager_score'] = null;
                }
            }

            $locked->update($changes);

            $review->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Run one guarded transition: lock the review, re-check the cycle and the
     * transition against the freshly read row, then apply the callback's
     * attribute changes inside the same transaction.
     *
     * @param  Closure(PerformanceReview): array<string, mixed>  $resolve
     */
    private function transition(PerformanceReview $review, string $to, Closure $resolve): void
    {
        DB::transaction(function () use ($review, $to, $resolve): void {
            $locked = $this->lock($review);

            $this->assertCycleOpen($locked);
            $this->assertTransition($locked, $to);

            $locked->update($resolve($locked));

            $review->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Re-read the review inside the current transaction holding a row lock, so
     * the status it reports cannot change underneath the transition.
     */
    private function lock(PerformanceReview $review): PerformanceReview
    {
        /** @var PerformanceReview $locked */
        $locked = PerformanceReview::query()
            ->with('cycle')
            ->lockForUpdate()
            ->findOrFail($review->getKey());

        return $locked;
    }
}
