<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerformanceReviewWorkflow;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * `PerformanceReview::scopePublishable()` is the single definition of "this
 * rating may be used downstream". These tests pin the three states that must
 * stay invisible to incentives, attrition, Report Studio, and HAV: unfinished
 * reviews, quarantined legacy rows, and reviews that were reopened after being
 * finalized.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();

    PerformanceReview::query()->delete();

    $this->cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus Publikasi',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => 'active',
    ]);
});

/**
 * A review at the given status under the shared active cycle.
 */
function makePublishableReview(object $context, array $overrides = []): PerformanceReview
{
    return PerformanceReview::create([
        'tenant_id' => $context->tenant->id,
        'cycle_id' => $context->cycle->id,
        'employee_id' => $context->employee->id,
        'status' => 'calibration',
        'manager_score' => 88,
        ...$overrides,
    ]);
}

it('treats only a completed, fully calibrated review as publishable', function (): void {
    $review = makePublishableReview($this);

    expect(PerformanceReview::query()->publishable()->count())->toBe(0);

    (new PerformanceReviewWorkflow)->calibrate($review, 90.0, $this->admin, 'Sesuai');

    expect(PerformanceReview::query()->publishable()->count())->toBe(1);
    expect($review->fresh()->isPublishable())->toBeTrue();
});

it('excludes a quarantined legacy review from publishable', function (): void {
    PerformanceReview::factory()->legacyCompleted()->create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
    ]);

    expect(PerformanceReview::query()->publishable()->count())->toBe(0);
});

it('stops publishing a review the moment it is reopened', function (): void {
    $review = makePublishableReview($this);
    $workflow = new PerformanceReviewWorkflow;
    $workflow->calibrate($review, 90.0, $this->admin, 'Sesuai');

    expect(PerformanceReview::query()->publishable()->count())->toBe(1);

    $workflow->reopen($review, 'calibration', $this->admin, 'Realisasi KPI salah');

    expect(PerformanceReview::query()->publishable()->count())->toBe(0);
});

it('keeps legacy reviews out of the Human Asset Value report', function (): void {
    $this->employee->update(['join_date' => now()->subYears(3)->toDateString()]);

    PerformanceReview::factory()->legacyCompleted()->create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
        'final_score' => 95,
    ]);

    actingAs($this->admin)
        ->get(route('avana.kinerja.hav'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kinerja/hav', false)
            ->has('rows', 0)
            ->where('kpis.rated', 0));
});

it('marks pre-workflow completed reviews as legacy when migrating', function (): void {
    // The migration quarantines exactly this shape: completed, scored, but with
    // nobody recorded as having calibrated it.
    $legacy = PerformanceReview::factory()->legacyCompleted()->create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
    ]);

    expect($legacy->is_legacy)->toBeTrue();
    expect($legacy->isPublishable())->toBeFalse();
    // The score is preserved rather than erased — it is history, just not a
    // rating anything may act on.
    expect($legacy->final_score)->not->toBeNull();
});
