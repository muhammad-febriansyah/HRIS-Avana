<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
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

    $this->workflow->calibrate($review, 90.0, $this->admin, 'Bagus');

    $review->refresh();
    expect($review->status)->toBe('completed');
    expect((float) $review->final_score)->toBe(90.0);
    expect((float) $review->calibrated_score)->toBe(90.0);
    expect($review->calibrated_by)->toBe($this->admin->id);
});

it('submitManagerScore ignores the manual score once KPI items exist', function (): void {
    $review = makeWorkflowReview($this->tenant->id, $this->employee->id, 'manager_review');
    $review->kpiItems()->create([
        'tenant_id' => $this->tenant->id,
        'source' => 'manual',
        'label' => 'X',
        'weight' => 100,
        'direction' => 'higher_better',
        'achievement_pct' => 55,
    ]);
    $review->update(['manager_score' => 55]);

    $this->workflow->submitManagerScore($review, 99.0, null);

    // 99.0 is discarded — the review has KPI items, so PerformanceKpiScorer
    // is the sole writer of manager_score, not this manual value.
    expect((float) $review->fresh()->manager_score)->toBe(55.0);
    expect($review->fresh()->status)->toBe('calibration');
});
