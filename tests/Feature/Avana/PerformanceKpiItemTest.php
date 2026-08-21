<?php

use App\Models\Employee;
use App\Models\KeyResult;
use App\Models\KpiIndicator;
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

    $this->cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus KPI',
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => 'active',
    ]);

    $this->review = PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $this->cycle->id,
        'employee_id' => $this->employee->id,
        'status' => 'pending',
    ]);

    $this->indicator = KpiIndicator::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Produktivitas',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);
});

it('adds a manual KPI item and recomputes the review manager_score', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'manual',
            'kpi_indicator_id' => $this->indicator->id,
            'weight' => 100,
            'target_value' => 100,
            'actual_value' => 80,
        ])
        ->assertSessionHas('success');

    $item = PerformanceKpiItem::where('review_id', $this->review->id)->firstOrFail();

    expect((float) $item->achievement_pct)->toBe(80.0);
    expect((float) $this->review->fresh()->manager_score)->toBe(80.0);
});

it('rejects a KPI item whose weight would push the review over 100%', function (): void {
    PerformanceKpiItem::create([
        'tenant_id' => $this->tenant->id,
        'review_id' => $this->review->id,
        'source' => 'manual',
        'kpi_indicator_id' => $this->indicator->id,
        'label' => 'Existing',
        'weight' => 70,
        'direction' => 'higher_better',
        'target_value' => 100,
        'actual_value' => 100,
        'achievement_pct' => 100,
    ]);

    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'manual',
            'kpi_indicator_id' => $this->indicator->id,
            'weight' => 40,
            'target_value' => 100,
            'actual_value' => 50,
        ])
        ->assertSessionHasErrors(['weight']);
});

it('computes weighted manager_score across multiple items', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'manual',
            'kpi_indicator_id' => $this->indicator->id,
            'weight' => 50,
            'target_value' => 100,
            'actual_value' => 100,
        ])
        ->assertSessionHas('success');

    $second = KpiIndicator::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Kualitas',
        'direction' => 'higher_better',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'manual',
            'kpi_indicator_id' => $second->id,
            'weight' => 50,
            'target_value' => 100,
            'actual_value' => 60,
        ])
        ->assertSessionHas('success');

    // (100*50 + 60*50) / 100 = 80
    expect((float) $this->review->fresh()->manager_score)->toBe(80.0);
});

it('adds a key-result-sourced item whose achievement is read-only and pulled live', function (): void {
    $objective = Objective::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'title' => 'Tingkatkan retensi',
        'level' => 'individual',
        'status' => 'active',
        'progress' => 0,
    ]);
    $keyResult = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'Turunkan turnover ke 5%',
        'target_value' => 100,
        'current_value' => 40,
        'progress' => 40,
    ]);

    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'key_result',
            'key_result_id' => $keyResult->id,
            'weight' => 100,
        ])
        ->assertSessionHas('success');

    $item = PerformanceKpiItem::where('review_id', $this->review->id)->firstOrFail();

    expect($item->source)->toBe('key_result');
    expect((float) $item->achievement_pct)->toBe(40.0);
    expect((float) $this->review->fresh()->manager_score)->toBe(40.0);

    // actual_value/target_value are not accepted for a key_result item.
    actingAs($this->admin)
        ->put(route('avana.kinerja.kpi-item.update', $item), [
            'weight' => 90,
        ])
        ->assertSessionHas('success');

    expect((float) $item->fresh()->weight)->toBe(90.0);
});

it('rejects a Key Result that does not belong to the reviewed employee', function (): void {
    $otherEmployee = Employee::forTenant($this->tenant->id)->where('id', '!=', $this->employee->id)->first()
        ?? Employee::create([
            'tenant_id' => $this->tenant->id,
            'employee_number' => 'EMP-KPI-1',
            'full_name' => 'Karyawan Lain',
            'employment_status' => 'permanent',
            'status' => 'active',
        ]);

    $objective = Objective::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $otherEmployee->id,
        'title' => 'Objective orang lain',
        'level' => 'individual',
        'status' => 'active',
        'progress' => 0,
    ]);
    $keyResult = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'KR orang lain',
        'target_value' => 100,
        'current_value' => 10,
        'progress' => 10,
    ]);

    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'key_result',
            'key_result_id' => $keyResult->id,
            'weight' => 50,
        ])
        ->assertStatus(422);
});

it('removing an item recomputes the review manager_score', function (): void {
    $first = PerformanceKpiItem::create([
        'tenant_id' => $this->tenant->id,
        'review_id' => $this->review->id,
        'source' => 'manual',
        'kpi_indicator_id' => $this->indicator->id,
        'label' => 'A',
        'weight' => 50,
        'direction' => 'higher_better',
        'achievement_pct' => 100,
    ]);
    PerformanceKpiItem::create([
        'tenant_id' => $this->tenant->id,
        'review_id' => $this->review->id,
        'source' => 'manual',
        'kpi_indicator_id' => $this->indicator->id,
        'label' => 'B',
        'weight' => 50,
        'direction' => 'higher_better',
        'achievement_pct' => 40,
    ]);
    $this->review->update(['manager_score' => 70]);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.kpi-item.destroy', $first))
        ->assertSessionHas('success');

    expect((float) $this->review->fresh()->manager_score)->toBe(40.0);
});

it('blocks KPI item mutation once the review is completed', function (): void {
    $this->review->update(['status' => 'completed']);

    actingAs($this->admin)
        ->post(route('avana.kinerja.kpi-item.store', $this->review), [
            'source' => 'manual',
            'kpi_indicator_id' => $this->indicator->id,
            'weight' => 50,
            'target_value' => 100,
            'actual_value' => 50,
        ])
        ->assertStatus(423);
});
