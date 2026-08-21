<?php

namespace App\Services;

use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Enforces the performance review lifecycle as a real state machine:
 * pending → self_review → manager_review → calibration → completed.
 *
 * Every mutation that changes a review's status or finalizes its score goes
 * through here instead of the controller writing `status`/`final_score`
 * directly, so a review can no longer skip self-assessment, manager scoring,
 * or calibration on the way to `completed`.
 *
 * Existing reviews created before this service existed are grandfathered:
 * nothing here retroactively re-validates or re-locks them, it only governs
 * transitions going forward.
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
     * Record the employee's self-assessment and hand the review to their
     * manager: pending|self_review → manager_review.
     */
    public function submitSelfAssessment(PerformanceReview $review, float $selfScore, ?string $notes): void
    {
        $this->assertCycleOpen($review);
        $this->assertTransition($review, 'manager_review');

        $review->update([
            'self_score' => $selfScore,
            'notes' => $notes ?? $review->notes,
            'status' => 'manager_review',
        ]);
    }

    /**
     * Record the manager's score and move the review into calibration:
     * manager_review → calibration.
     *
     * `$managerScore` is only written when the review has no KPI items yet
     * (legacy/manual mode); once KPI items exist, {@see PerformanceKpiScorer}
     * is the sole writer of `manager_score` and this value is ignored.
     */
    public function submitManagerScore(PerformanceReview $review, ?float $managerScore, ?string $reviewDate): void
    {
        $this->assertCycleOpen($review);
        $this->assertTransition($review, 'calibration');

        $data = [
            'status' => 'calibration',
            'review_date' => $reviewDate ?? $review->review_date,
        ];

        if ($review->kpiItems()->doesntExist() && $managerScore !== null) {
            $data['manager_score'] = $managerScore;
        }

        $review->update($data);
    }

    /**
     * Finalize a review: calibration → completed. This is the only path that
     * may set `final_score`/`status=completed`.
     */
    public function calibrate(PerformanceReview $review, float $calibratedScore, User $actor, ?string $notes): void
    {
        $this->assertCycleOpen($review);
        $this->assertTransition($review, 'completed');

        $review->update([
            'calibrated_score' => $calibratedScore,
            'final_score' => $calibratedScore,
            'calibrated_by' => $actor->id,
            'calibrated_at' => Carbon::now(),
            'notes' => $notes ?? $review->notes,
            'status' => 'completed',
        ]);
    }

    /**
     * Reopen a completed review, sending it back to an earlier stage for
     * correction. This deliberately bypasses the forward-only transition map —
     * it is the one sanctioned backward move, gated by the caller's permission
     * check and always attributed via the reason appended to `notes`.
     */
    public function reopen(PerformanceReview $review, string $to, User $actor, string $reason): void
    {
        abort_unless($review->status === 'completed', 422, 'Hanya penilaian selesai yang dapat dibuka kembali.');
        abort_unless(in_array($to, self::REOPEN_TARGETS, true), 422);

        $review->update([
            'status' => $to,
            'notes' => trim(($review->notes ?? '')."\n[Dibuka kembali oleh {$actor->name}] {$reason}"),
        ]);
    }
}
