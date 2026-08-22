<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewRevision;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerformanceReviewWorkflow;
use Database\Seeders\AvanaDemoSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();
    $this->workflow = new PerformanceReviewWorkflow;
});

/**
 * Create a performance review directly (bypassing the workflow service, the
 * way a factory/seeder would) at the given status under an active cycle.
 */
function makeWorkflowReview(int $tenantId, int $employeeId, string $status): PerformanceReview
{
    $cycle = PerformanceCycle::create([
        'tenant_id' => $tenantId,
        'name' => 'Siklus Workflow',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => 'active',
    ]);

    return PerformanceReview::create([
        'tenant_id' => $tenantId,
        'cycle_id' => $cycle->id,
        'employee_id' => $employeeId,
        'status' => $status,
    ]);
}

it('allows every valid forward transition', function (string $from, string $to): void {
    expect($this->workflow->canTransition($from, $to))->toBeTrue();
})->with([
    ['pending', 'self_review'],
    ['pending', 'manager_review'],
    ['self_review', 'manager_review'],
    ['manager_review', 'calibration'],
    ['calibration', 'completed'],
]);

it('rejects invalid transitions', function (string $from, string $to): void {
    expect($this->workflow->canTransition($from, $to))->toBeFalse();
})->with([
    ['pending', 'calibration'],
    ['pending', 'completed'],
    ['self_review', 'calibration'],
    ['manager_review', 'completed'],
    ['calibration', 'manager_review'],
    ['completed', 'calibration'],
    ['completed', 'pending'],
]);

it('assertTransition aborts with 422 on an invalid move', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'pending');

    expect(fn () => $this->workflow->assertTransition($review, 'completed'))
        ->toThrow(HttpException::class);
});

it('assertCycleOpen aborts with 422 when the cycle is not active', function (): void {
    $cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus Draf',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => 'draft',
    ]);
    $review = PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycle->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    expect(fn () => $this->workflow->assertCycleOpen($review))
        ->toThrow(HttpException::class);
});

it('assertMutable aborts with 423 once a review is completed', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'completed');

    expect(fn () => $this->workflow->assertMutable($review))
        ->toThrow(HttpException::class);
});

it('reopen only works from completed, to manager_review or calibration', function (): void {
    $notCompleted = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');

    expect(fn () => $this->workflow->reopen($notCompleted, 'manager_review', $this->admin, 'x'))
        ->toThrow(HttpException::class);

    $completed = makeWorkflowReview($this->tenant->id, $this->employee->id, 'completed');

    expect(fn () => $this->workflow->reopen($completed, 'pending', $this->admin, 'x'))
        ->toThrow(HttpException::class);

    $this->workflow->reopen($completed, 'calibration', $this->admin, 'Salah kalibrasi');

    expect($completed->fresh()->status)->toBe('calibration');
    expect($completed->fresh()->notes)->toContain('Salah kalibrasi');
});

it('calibrate requires the calibration status and sets final_score', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'calibration');
    $review->update(['manager_score' => 88]);

    $this->workflow->calibrate($review, 90.0, $this->admin, 'Bagus');

    $review->refresh();
    expect($review->status)->toBe('completed');
    expect((float) $review->final_score)->toBe(90.0);
    expect((float) $review->calibrated_score)->toBe(90.0);
    expect($review->calibrated_by)->toBe($this->admin->id);
    expect($review->isPublishable())->toBeTrue();
});

it('calibrate refuses a review that never got a manager score', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'calibration');

    expect(fn () => $this->workflow->calibrate($review, 90.0, $this->admin, 'Bagus'))
        ->toThrow(HttpException::class);

    expect($review->fresh()->status)->toBe('calibration');
});

it('calibrate demands a written reason when it departs far from the manager score', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'calibration');
    $review->update(['manager_score' => 60]);

    expect(fn () => $this->workflow->calibrate($review, 95.0, $this->admin, null))
        ->toThrow(HttpException::class);

    $this->workflow->calibrate($review, 95.0, $this->admin, 'Dinaikkan setelah kalibrasi lintas divisi');

    expect($review->fresh()->status)->toBe('completed');
});

it('reopen clears the finalized scores and snapshots them as a revision', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'calibration');
    $review->update(['manager_score' => 88]);
    $this->workflow->calibrate($review, 90.0, $this->admin, 'Bagus');

    $this->workflow->reopen($review, 'calibration', $this->admin, 'Salah input realisasi');

    $review->refresh();
    expect($review->status)->toBe('calibration');
    expect($review->final_score)->toBeNull();
    expect($review->calibrated_score)->toBeNull();
    expect($review->calibrated_by)->toBeNull();
    expect($review->calibrated_at)->toBeNull();
    expect($review->isPublishable())->toBeFalse();

    $revision = $review->revisions()->firstOrFail();
    expect((float) $revision->final_score)->toBe(90.0);
    expect($revision->reopened_by)->toBe($this->admin->id);
    expect($revision->reason)->toBe('Salah input realisasi');
});

it('submitManagerScore ignores the manual score once KPI items exist', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $review->update(['scoring_mode' => 'kpi']);
    $review->kpiItems()->create([
        'tenant_id' => $this->tenant->id,
        'source' => 'manual',
        'label' => 'X',
        'weight' => 100,
        'direction' => 'higher_better',
        'target_value' => 100,
        'actual_value' => 55,
        'achievement_pct' => 55,
    ]);
    $review->update(['manager_score' => 55]);

    $this->workflow->submitManagerScore($review, 99.0, '2026-06-30', $this->admin);

    // 99.0 is discarded — the review has KPI items, so PerformanceKpiScorer
    // is the sole writer of manager_score, not this manual value.
    expect((float) $review->fresh()->manager_score)->toBe(55.0);
    expect($review->fresh()->status)->toBe('calibration');
});

it('submitManagerScore refuses an incomplete KPI scorecard', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $review->update(['scoring_mode' => 'kpi']);
    $review->kpiItems()->create([
        'tenant_id' => $this->tenant->id,
        'source' => 'manual',
        'label' => 'X',
        // Only 60% of the weight budget is assigned.
        'weight' => 60,
        'direction' => 'higher_better',
        'target_value' => 100,
        'actual_value' => 55,
        'achievement_pct' => 55,
    ]);

    expect(fn () => $this->workflow->submitManagerScore($review, null, '2026-06-30', $this->admin))
        ->toThrow(HttpException::class);

    expect($review->fresh()->status)->toBe('manager_review');
});

it('submitManagerScore refuses an empty click with no score and no KPI items', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');

    expect(fn () => $this->workflow->submitManagerScore($review, null, '2026-06-30', $this->admin))
        ->toThrow(HttpException::class);

    expect($review->fresh()->status)->toBe('manager_review');
});

it('submitManagerScore refuses a KPI item whose realisation is still blank', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $review->update(['scoring_mode' => 'kpi']);
    $review->kpiItems()->create([
        'tenant_id' => $this->tenant->id,
        'source' => 'manual',
        'label' => 'X',
        'weight' => 100,
        'direction' => 'higher_better',
        'target_value' => 100,
        'actual_value' => null,
        'achievement_pct' => 0,
    ]);

    expect(fn () => $this->workflow->submitManagerScore($review, null, '2026-06-30', $this->admin))
        ->toThrow(HttpException::class);
});

it('records who entered the manager score', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');

    $this->workflow->submitManagerScore($review, 80.0, '2026-06-30', $this->admin);

    $review->refresh();
    expect($review->manager_scored_by)->toBe($this->admin->id);
    expect($review->manager_scored_at)->not->toBeNull();
});

it('refuses to let the manager scorer also calibrate', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $this->workflow->submitManagerScore($review, 80.0, '2026-06-30', $this->admin);

    expect(fn () => $this->workflow->calibrate($review, 82.0, $this->admin, 'Setuju'))
        ->toThrow(HttpException::class);

    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->workflow->calibrate($review, 82.0, $other, 'Setuju');

    expect($review->fresh()->status)->toBe('completed');
});

it('clears the manager score when a review is reopened to manager review', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $this->workflow->submitManagerScore($review, 80.0, '2026-06-30', $this->admin);

    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->workflow->calibrate($review, 82.0, $other, 'Setuju');

    $this->workflow->reopen($review, 'manager_review', $this->admin, 'Nilai atasan keliru');

    $review->refresh();
    expect($review->status)->toBe('manager_review');
    // Left in place, the rejected score could walk straight back into
    // calibration without the manager re-entering anything.
    expect($review->manager_score)->toBeNull();
    expect($review->manager_scored_by)->toBeNull();
    expect($review->manager_scored_at)->toBeNull();
});

it('keeps the reopen snapshot after the review itself is deleted', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $this->workflow->submitManagerScore($review, 80.0, '2026-06-30', $this->admin);

    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->workflow->calibrate($review, 82.0, $other, 'Setuju');
    $this->workflow->reopen($review, 'calibration', $this->admin, 'Perlu ditinjau ulang');

    $revisionId = $review->revisions()->value('id');
    $employeeId = $review->employee_id;

    $review->delete();

    $revision = PerformanceReviewRevision::find($revisionId);

    expect($revision)->not->toBeNull();
    expect($revision->review_id)->toBeNull();
    expect($revision->employee_id)->toBe($employeeId);
    expect((float) $revision->final_score)->toBe(82.0);
});

it('refuses a review date outside the cycle period', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');

    expect(fn () => $this->workflow->submitManagerScore($review, 80.0, '2027-03-01', $this->admin))
        ->toThrow(HttpException::class);

    expect($review->fresh()->status)->toBe('manager_review');
});

it('refuses a KPI review whose items were all removed', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $review->update(['scoring_mode' => 'kpi', 'manager_score' => 90]);

    expect(fn () => $this->workflow->submitManagerScore($review, null, '2026-06-30', $this->admin))
        ->toThrow(HttpException::class);
});
