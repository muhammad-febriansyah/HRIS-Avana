<?php

use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceReview;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
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
                ->has('route_key')
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
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'active']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'reviewer_id' => null,
            'notes' => 'Kinerja baik',
            'review_date' => '2026-07-01',
        ])
        ->assertRedirect(route('avana.kinerja'))
        ->assertSessionHas('success');

    $review = PerformanceReview::where('employee_id', $employee->id)
        ->where('cycle_id', $cycle->id)
        ->firstOrFail();

    expect($review->tenant_id)->toBe($this->tenant->id);
    expect($review->status)->toBe('pending');
});

it('creating a review under a non-active cycle is rejected', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'draft']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
        ])
        ->assertStatus(422);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => '',
            'employee_id' => '',
        ])
        ->assertSessionHasErrors(['cycle_id', 'employee_id']);
});

it('validates the review against tenant-scoped cycles and employees', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignCycle = makePerformanceCycle($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => $foreignCycle->id,
            'employee_id' => 99999,
        ])
        ->assertSessionHasErrors(['cycle_id', 'employee_id']);
});

it('updates an existing review', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'active']);
    $review = makePerformanceReview($this->tenant->id, ['status' => 'pending', 'cycle_id' => $cycle->id]);

    actingAs($this->admin)
        ->put(route('avana.kinerja.update', $review), [
            'cycle_id' => $review->cycle_id,
            'employee_id' => $review->employee_id,
            'reviewer_id' => null,
            'notes' => 'Diperbarui',
            'review_date' => '2026-07-10',
        ])
        ->assertRedirect(route('avana.kinerja'))
        ->assertSessionHas('success');

    $review->refresh();

    expect($review->status)->toBe('pending');
    expect($review->notes)->toBe('Diperbarui');
});

it('a completed review cannot be updated, deleted, or given feedback', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'active']);
    $review = makePerformanceReview($this->tenant->id, ['status' => 'completed', 'cycle_id' => $cycle->id]);

    actingAs($this->admin)
        ->put(route('avana.kinerja.update', $review), [
            'cycle_id' => $review->cycle_id,
            'employee_id' => $review->employee_id,
        ])
        ->assertStatus(423);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.destroy', $review))
        ->assertStatus(423);

    actingAs($this->admin)
        ->post(route('avana.kinerja.feedback.store', $review), ['type' => 'peer', 'rating' => 80])
        ->assertStatus(423);
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

it('moves a cycle through draft to active to closed', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'draft']);

    actingAs($this->admin)
        ->patch(route('avana.kinerja.cycle.status', $cycle), ['status' => 'active'])
        ->assertSessionHas('success');
    expect($cycle->fresh()->status)->toBe('active');

    actingAs($this->admin)
        ->patch(route('avana.kinerja.cycle.status', $cycle), ['status' => 'closed'])
        ->assertSessionHas('success');
    expect($cycle->fresh()->status)->toBe('closed');
});

it('rejects an invalid cycle status transition', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'draft']);

    actingAs($this->admin)
        ->patch(route('avana.kinerja.cycle.status', $cycle), ['status' => 'closed'])
        ->assertStatus(422);
});

it('submits scores for a review', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'active']);
    $review = makePerformanceReview($this->tenant->id, ['status' => 'manager_review', 'cycle_id' => $cycle->id]);

    actingAs($this->admin)
        ->post(route('avana.kinerja.score', $review), [
            'manager_score' => 80,
            'review_date' => '2026-07-15',
        ])
        ->assertSessionHas('success');

    $review->refresh();

    expect($review->status)->toBe('calibration');
    expect((float) $review->manager_score)->toBe(80.0);
    expect($review->review_date->toDateString())->toBe('2026-07-15');
});

it('rejects submitting a score from an invalid status', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id, ['status' => 'active']);
    $review = makePerformanceReview($this->tenant->id, ['status' => 'pending', 'cycle_id' => $cycle->id]);

    actingAs($this->admin)
        ->post(route('avana.kinerja.score', $review), ['manager_score' => 80])
        ->assertStatus(422);
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

it('renders the edit screen with feedbacks and feedback type options', function (): void {
    $review = makePerformanceReview($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    PerformanceFeedback::create([
        'tenant_id' => $this->tenant->id,
        'review_id' => $review->id,
        'reviewer_id' => $employee->id,
        'type' => 'peer',
        'rating' => 88,
        'comment' => 'Kolaboratif',
    ]);

    actingAs($this->admin)
        ->get(route('avana.kinerja.edit', $review))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kinerja/edit', false)
            ->has('feedbacks.0', fn (Assert $row) => $row
                ->has('id')
                ->has('type')
                ->has('reviewer_id')
                ->has('reviewer_name')
                ->has('rating')
                ->has('comment')
                ->has('created_at'))
            ->has('feedbackTypes')
            ->has('employees'));
});

it('stores a 360 feedback entry for a review', function (): void {
    $review = makePerformanceReview($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kinerja.feedback.store', $review), [
            'type' => 'manager',
            'reviewer_id' => $employee->id,
            'rating' => 92.5,
            'comment' => 'Pemimpin yang baik',
        ])
        ->assertSessionHas('success');

    $feedback = PerformanceFeedback::where('review_id', $review->id)->firstOrFail();

    expect($feedback->tenant_id)->toBe($this->tenant->id);
    expect($feedback->type)->toBe('manager');
    expect($feedback->reviewer_id)->toBe($employee->id);
    expect((float) $feedback->rating)->toBe(92.5);
});

it('validates the feedback type and rating range', function (): void {
    $review = makePerformanceReview($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kinerja.feedback.store', $review), [
            'type' => 'invalid',
            'rating' => 150,
        ])
        ->assertSessionHasErrors(['type', 'rating']);
});

it('deletes a 360 feedback entry', function (): void {
    $review = makePerformanceReview($this->tenant->id);
    $feedback = PerformanceFeedback::create([
        'tenant_id' => $this->tenant->id,
        'review_id' => $review->id,
        'type' => 'self',
        'rating' => 70,
        'comment' => 'Refleksi diri',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.feedback.destroy', $feedback))
        ->assertSessionHas('success');

    expect(PerformanceFeedback::find($feedback->id))->toBeNull();
});

it('returns 404 when adding feedback to a review from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makePerformanceReview($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.kinerja.feedback.store', $foreign), [
            'type' => 'peer',
            'rating' => 80,
        ])
        ->assertNotFound();

    expect(PerformanceFeedback::where('review_id', $foreign->id)->count())->toBe(0);
});

it('returns 404 when deleting feedback from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makePerformanceReview($otherTenant->id);
    $feedback = PerformanceFeedback::create([
        'tenant_id' => $otherTenant->id,
        'review_id' => $foreign->id,
        'type' => 'peer',
        'rating' => 80,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.kinerja.feedback.destroy', $feedback))
        ->assertNotFound();

    expect(PerformanceFeedback::find($feedback->id))->not->toBeNull();
});

it('enforces performance actions at the permission level', function (): void {
    $role = Role::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'performance-viewer',
        'name' => 'Performance Viewer',
        'is_system' => false,
    ]);
    $role->permissions()->syncWithoutDetaching(
        Permission::where('code', 'performance.view')->pluck('id'),
    );

    $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $viewer->roles()->sync([$role->id]);

    actingAs($viewer)
        ->get(route('avana.kinerja'))
        ->assertOk();

    actingAs($viewer)
        ->post(route('avana.kinerja.store'), [
            'cycle_id' => 1,
            'employee_id' => 1,
            'status' => 'pending',
        ])
        ->assertForbidden();
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

it('renders the human asset value report ranked by HAV index', function (): void {
    $cycle = makePerformanceCycle($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $employee->update(['join_date' => now()->subYears(3)->toDateString()]);

    PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
        'self_score' => 80,
        'manager_score' => 90,
        'final_score' => 90,
        'status' => 'completed',
    ]);

    actingAs($this->admin)
        ->get(route('avana.kinerja.hav'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kinerja/hav', false)
            ->has('rows.0', fn (Assert $row) => $row
                ->where('employee_id', $employee->id)
                ->where('category', 'Bintang')
                ->has('score')
                ->has('tenure_years')
                ->has('hav_index')
                ->etc())
            ->has('kpis.rated')
            ->has('kpis.avg_hav')
            ->has('kpis.stars')
            ->has('kpis.at_risk'));
});
