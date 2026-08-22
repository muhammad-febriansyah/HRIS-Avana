<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

/**
 * The operational way out of legacy quarantine. It deliberately cannot
 * manufacture a calibration record — the only thing it may do is send a review
 * back into the workflow so a real person scores and calibrates it.
 */
beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();

    PerformanceReview::query()->delete();

    $this->cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus Remediasi',
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'status' => 'active',
    ]);

    $this->legacy = PerformanceReview::factory()->legacyCompleted()->create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
        'final_score' => 88,
    ]);
});

it('lists quarantined reviews without touching them', function (): void {
    $this->artisan('avana:remediate-performance-legacy')
        ->expectsOutputToContain('excluded from incentives')
        ->assertSuccessful();

    expect($this->legacy->fresh()->is_legacy)->toBeTrue();
    expect((float) $this->legacy->fresh()->final_score)->toBe(88.0);
});

it('sends a quarantined review back to manager review without inventing a calibration', function (): void {
    $this->artisan('avana:remediate-performance-legacy --reopen')
        ->expectsConfirmation('Send 1 review(s) back to manager review? Their recorded score is kept as a note, not as a rating.', 'yes')
        ->assertSuccessful();

    $review = $this->legacy->fresh();

    expect($review->status)->toBe('manager_review');
    expect($review->is_legacy)->toBeFalse();
    // No score survives the move: the whole problem was a rating nobody signed.
    expect($review->final_score)->toBeNull();
    expect($review->manager_score)->toBeNull();
    expect($review->calibrated_at)->toBeNull();
    expect($review->isPublishable())->toBeFalse();
    // The old figure is preserved as context, not as a rating.
    expect($review->notes)->toContain('88');
});

it('skips reviews whose cycle is no longer active', function (): void {
    $this->cycle->update(['status' => 'closed']);

    $this->artisan('avana:remediate-performance-legacy --reopen')
        ->expectsOutputToContain('will be skipped')
        ->assertSuccessful();

    expect($this->legacy->fresh()->status)->toBe('completed');
    expect($this->legacy->fresh()->is_legacy)->toBeTrue();
});
