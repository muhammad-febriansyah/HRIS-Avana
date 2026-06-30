<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a performance cycle for the given tenant.
 */
function makePerformanceCycle(int $tenantId, array $overrides = []): PerformanceCycle
{
    return PerformanceCycle::factory()->create(array_merge([
        'tenant_id' => $tenantId,
    ], $overrides));
}

/**
 * Create a performance review under the given tenant.
 */
function makePerformanceReview(int $tenantId, array $overrides = []): PerformanceReview
{
    $cycleId = $overrides['cycle_id'] ?? makePerformanceCycle($tenantId)->id;

    return PerformanceReview::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'cycle_id' => $cycleId,
    ], $overrides));
}

it('renders the performance index with the expected props', function (): void {
    makePerformanceReview($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.kinerja'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kinerja/index', false)
            ->has('reviews.0', fn (Assert $row) => $row
                ->has('id')
                ->has('cycle_id')
                ->has('cycle')
                ->has('employee_id')
                ->has('employee')
                ->has('employee_number')
                ->has('reviewer_id')
                ->has('reviewer')
                ->has('self_score')
                ->has('manager_score')
                ->has('final_score')
                ->has('status')
                ->has('notes')
                ->has('review_date'))
            ->has('cycles.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('period_start')
                ->has('period_end')
                ->has('status')
                ->has('description')
                ->has('reviews_count'))
            ->has('employees')
            ->has('cycleOptions')
            ->has('statuses')
            ->has('cycleStatuses')
            ->has('kpis'));
});

it('only lists reviews that belong to the current tenant', function (): void {
    makePerformanceReview($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makePerformanceReview($otherTenant->id);

    $tenantTotal = PerformanceReview::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.kinerja'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews', $tenantTotal));
});

it('creates a review scoped to the current tenant', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'reviewer_id' => null,
            'self_score' => 80,
            'manager_score' => 85,
            'final_score' => 82.5,
            'status' => 'completed',
            'notes' => 'Kinerja baik',
            'review_date' => '2026-07-01',
        ])
        ->assertRedirect(route('avana.kinerja'))
        ->assertSessionHas('success');

    $review = PerformanceReview::where('employee_id', $employee->id)
        ->where('cycle_id', $cycle->id)
        ->firstOrFail();

    expect($review->tenant_id)->toBe($this->tenant->id);
    expect($review->status)->toBe('completed');
    expect((float) $review->final_score)->toBe(82.5);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => '',
            'employee_id' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['cycle_id', 'employee_id', 'status']);
});

it('validates the review against tenant-scoped cycles and employees', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignCycle = makePerformanceCycle($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => $foreignCycle->id,
            'employee_id' => 99999,
            'status' => 'pending',
        ])
        ->assertSessionHasErrors(['cycle_id', 'employee_id']);
});

it('updates an existing review', function (): void {
    $review = makePerformanceReview($this->tenant->id, ['status' => 'pending']);

    actingAs($this->admin)
        ->put(route('avana.kinerja.update', $review), [
            'cycle_id' => $review->cycle_id,
            'employee_id' => $review->employee_id,
            'reviewer_id' => null,
            'self_score' => 90,
            'manager_score' => 88,
            'final_score' => 89,
            'status' => 'completed',
            'notes' => 'Diperbarui',
            'review_date' => '2026-07-10',
        ])
        ->assertRedirect(route('avana.kinerja'))
        ->assertSessionHas('success');

    $review->refresh();

    expect($review->status)->toBe('completed');
    expect((float) $review->final_score)->toBe(89.0);
    expect($review->notes)->toBe('Diperbarui');
});

it('deletes a review', function (): void {
    $review = makePerformanceReview($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.destroy', $review))
        ->assertSessionHas('success');

    expect(PerformanceReview::find($review->id))->toBeNull();
});

it('adds a cycle scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.cycle.store'), [
            'name' => 'Penilaian Q3 2026',
            'period_start' => '2026-07-01',
            'period_end' => '2026-09-30',
            'status' => 'active',
            'description' => 'Siklus kuartal tiga',
        ])
        ->assertRedirect(route('avana.kinerja'))
        ->assertSessionHas('success');

    $cycle = PerformanceCycle::where('name', 'Penilaian Q3 2026')->firstOrFail();

    expect($cycle->tenant_id)->toBe($this->tenant->id);
    expect($cycle->status)->toBe('active');
});

it('validates required fields when adding a cycle', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.cycle.store'), [
            'name' => '',
            'period_start' => '',
            'period_end' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'period_start', 'period_end', 'status']);
});

it('submits scores for a review', function (): void {
    $review = makePerformanceReview($this->tenant->id, ['status' => 'pending']);

    actingAs($this->admin)
        ->post(route('avana.kinerja.score', $review), [
            'self_score' => 75,
            'manager_score' => 80,
            'final_score' => 78,
            'status' => 'completed',
            'review_date' => '2026-07-15',
        ])
        ->assertSessionHas('success');

    $review->refresh();

    expect($review->status)->toBe('completed');
    expect((float) $review->self_score)->toBe(75.0);
    expect((float) $review->manager_score)->toBe(80.0);
    expect($review->review_date->toDateString())->toBe('2026-07-15');
});

it('returns 404 when updating a review from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makePerformanceReview($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.kinerja.update', $foreign), [
            'cycle_id' => $foreign->cycle_id,
            'employee_id' => $foreign->employee_id,
            'status' => 'completed',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a review from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makePerformanceReview($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.destroy', $foreign))
        ->assertNotFound();

    expect(PerformanceReview::find($foreign->id))->not->toBeNull();
});

it('returns 404 when submitting a score for a review from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makePerformanceReview($otherTenant->id, ['status' => 'pending']);

    actingAs($this->admin)
        ->post(route('avana.kinerja.score', $foreign), [
            'final_score' => 100,
            'status' => 'completed',
        ])
        ->assertNotFound();

    $foreign->refresh();

    expect($foreign->status)->toBe('pending');
});

it('forbids a plain employee from listing or creating reviews', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.kinerja'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => 1,
            'employee_id' => 1,
            'status' => 'pending',
        ])
        ->assertForbidden();
});
