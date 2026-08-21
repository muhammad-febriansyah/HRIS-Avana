<?php

use App\Models\Employee;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\PerformanceCycle;
use App\Models\PerformanceKpiItem;
use App\Models\PerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();

    $cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus Sync',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => 'active',
    ]);

    $this->review = PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycle->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->objective = Objective::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'title' => 'Tingkatkan retensi',
        'level' => 'individual',
        'status' => 'active',
        'progress' => 0,
    ]);

    $this->keyResult = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $this->objective->id,
        'title' => 'Turunkan turnover',
        'target_value' => 100,
        'current_value' => 20,
        'progress' => 20,
    ]);

    $this->item = PerformanceKpiItem::create([
        'tenant_id' => $this->tenant->id,
        'review_id' => $this->review->id,
        'source' => 'key_result',
        'key_result_id' => $this->keyResult->id,
        'label' => $this->keyResult->title,
        'weight' => 100,
        'direction' => 'higher_better',
        'achievement_pct' => 20,
    ]);
    $this->review->update(['manager_score' => 20]);
});

it('updating a Key Result live-syncs the linked KPI item and review score', function (): void {
    actingAs($this->admin)
        ->put(route('avana.okr.kr.update', $this->keyResult), [
            'title' => $this->keyResult->title,
            'target_value' => 100,
            'current_value' => 75,
        ])
        ->assertSessionHas('success');

    expect((float) $this->item->fresh()->achievement_pct)->toBe(75.0);
    expect((float) $this->review->fresh()->manager_score)->toBe(75.0);
});

it('deleting a Key Result removes its linked KPI item', function (): void {
    actingAs($this->admin)
        ->delete(route('avana.okr.kr.destroy', $this->keyResult))
        ->assertSessionHas('success');

    expect(PerformanceKpiItem::find($this->item->id))->toBeNull();
    // recomputeManagerScore no-ops on a review with zero KPI items (legacy
    // manual mode), so the last computed score is left as-is rather than
    // reset — the reviewer is expected to add a replacement item or enter a
    // manual score going forward.
    expect((float) $this->review->fresh()->manager_score)->toBe(20.0);
});
