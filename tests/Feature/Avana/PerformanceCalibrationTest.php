<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();

    $cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus 2026',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => 'active',
    ]);

    $this->review = PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycle->id,
        'employee_id' => $this->employee->id,
        'self_score' => 80,
        'manager_score' => 85,
        'status' => 'manager_review',
    ]);
});

it('calibrates a review, sets the final score and completes it', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.calibrate', $this->review), [
            'calibrated_score' => 88,
            'notes' => 'Naik sesuai kalibrasi tim',
        ])
        ->assertSessionHas('success');

    $review = $this->review->fresh();

    expect((float) $review->calibrated_score)->toBe(88.0);
    expect((float) $review->final_score)->toBe(88.0);
    expect($review->calibrated_by)->toBe($this->admin->id);
    expect($review->status)->toBe('completed');
});

it('requires a calibrated score', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.calibrate', $this->review), ['notes' => 'x'])
        ->assertSessionHasErrors('calibrated_score');
});
