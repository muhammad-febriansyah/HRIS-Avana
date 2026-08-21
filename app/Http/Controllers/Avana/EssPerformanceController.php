<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceReview;
use App\Services\PerformanceReviewWorkflow;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kinerja Saya" — the employee's own performance reviews, plus the one step of
 * the cycle that belongs to them: submitting their self-assessment.
 *
 * Manager scoring and calibration stay on the admin screen; those fields are
 * shown here only once they have been filled in.
 */
class EssPerformanceController extends Controller
{
    use ResolvesApiEmployee;

    /**
     * Indonesian labels for the review status enum, mirroring
     * {@see PerformanceController}.
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'self_review' => 'Penilaian Mandiri',
        'manager_review' => 'Penilaian Atasan',
        'calibration' => 'Kalibrasi',
        'completed' => 'Selesai',
    ];

    /**
     * Stages at which the employee may still enter their self-assessment.
     *
     * @var array<int, string>
     */
    private const SELF_REVIEW_STAGES = ['pending', 'self_review'];

    /**
     * The employee's reviews, newest cycle first.
     */
    public function index(Request $request): Response
    {
        $employee = $this->currentEmployee($request);

        $reviews = PerformanceReview::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with(['cycle:id,name,period_start,period_end,status', 'feedbacks:id,review_id,type,rating,comment'])
            ->orderByDesc('id')
            ->get();

        $scored = $reviews->map(fn (PerformanceReview $review): ?float => $this->effectiveScore($review))
            ->filter(fn (?float $score): bool => $score !== null);

        return Inertia::render('avana/saya/kinerja', [
            'reviews' => $reviews->map(fn (PerformanceReview $review): array => $this->shape($review))->values(),
            'summary' => [
                'total' => $reviews->count(),
                'completed' => $reviews->where('status', 'completed')->count(),
                'awaiting_self' => $reviews->whereIn('status', self::SELF_REVIEW_STAGES)->count(),
                'latest_score' => $this->effectiveScore($reviews->first()),
                'average_score' => $scored->isNotEmpty() ? round($scored->avg(), 1) : null,
            ],
        ]);
    }

    /**
     * Record the employee's self-assessment and hand the review to their
     * manager. Only their own review, and only while it is still at that stage.
     */
    public function submitSelfScore(Request $request, PerformanceReview $review): RedirectResponse
    {
        $employee = $this->currentEmployee($request);

        abort_if(
            (int) $review->tenant_id !== (int) $employee->tenant_id
            || (int) $review->employee_id !== (int) $employee->id,
            404,
        );

        abort_unless(
            in_array($review->status, self::SELF_REVIEW_STAGES, true),
            422,
            'Penilaian mandiri untuk siklus ini sudah ditutup.',
        );

        $data = $request->validate([
            'self_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'self_score.required' => 'Nilai mandiri wajib diisi.',
            'self_score.numeric' => 'Nilai mandiri harus berupa angka.',
            'self_score.min' => 'Nilai mandiri minimal 0.',
            'self_score.max' => 'Nilai mandiri maksimal 100.',
        ]);

        // Hands off to the manager via PerformanceReviewWorkflow; scoring and
        // calibration are theirs. The workflow also enforces the review's
        // cycle is still active, which the stage check above does not.
        (new PerformanceReviewWorkflow)->submitSelfAssessment($review, (float) $data['self_score'], $data['notes'] ?? null);

        return back()->with('success', 'Penilaian mandiri terkirim');
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(PerformanceReview $review): array
    {
        return [
            'id' => $review->id,
            'route_key' => $review->public_id,
            'cycle' => $review->cycle?->name,
            'period_start' => $this->dateString($review->cycle?->period_start),
            'period_end' => $this->dateString($review->cycle?->period_end),
            'self_score' => $this->score($review->self_score),
            'manager_score' => $this->score($review->manager_score),
            'final_score' => $this->score($review->final_score),
            'calibrated_score' => $this->score($review->calibrated_score),
            'effective_score' => $this->effectiveScore($review),
            'status' => $review->status,
            'status_label' => self::STATUS_LABELS[$review->status] ?? $review->status,
            'review_date' => $this->dateString($review->review_date),
            'notes' => $review->notes,
            'can_submit_self' => in_array($review->status, self::SELF_REVIEW_STAGES, true),
            // Reviewer identity is deliberately withheld: peer feedback is only
            // candid while it stays unattributed to the person being reviewed.
            'feedbacks' => $review->feedbacks->map(fn (PerformanceFeedback $feedback): array => [
                'id' => $feedback->id,
                'type' => $feedback->type,
                'rating' => $this->score($feedback->rating),
                'comment' => $feedback->comment,
            ])->values(),
        ];
    }

    /**
     * The score that represents the review right now: calibrated beats final,
     * final beats manager, manager beats self.
     */
    private function effectiveScore(?PerformanceReview $review): ?float
    {
        if ($review === null) {
            return null;
        }

        foreach (['calibrated_score', 'final_score', 'manager_score', 'self_score'] as $field) {
            if ($review->{$field} !== null) {
                return (float) $review->{$field};
            }
        }

        return null;
    }

    /**
     * Cast a nullable decimal column to a float the page can render.
     */
    private function score(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * Normalise a date cast back to a plain Y-m-d string.
     */
    private function dateString(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }
}
