<?php

use App\Models\Employee;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\PerformanceCycle;
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
 * Create an objective under the given tenant.
 */
function makeObjective(int $tenantId, array $overrides = []): Objective
{
    return Objective::create(array_merge([
        'tenant_id' => $tenantId,
        'title' => 'Tingkatkan retensi karyawan',
        'level' => 'team',
        'status' => 'active',
        'progress' => 0,
    ], $overrides));
}

it('renders the okr index with the expected props', function (): void {
    $objective = makeObjective($this->tenant->id);
    KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'Turunkan turnover ke 5%',
        'target_value' => 100,
        'current_value' => 40,
        'unit' => '%',
        'progress' => 40,
    ]);

    actingAs($this->admin)
        ->get(route('avana.okr'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/okr/index', false)
            ->has('objectives.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('description')
                ->has('level')
                ->has('perspective')
                ->has('status')
                ->has('progress')
                ->has('cycle_id')
                ->has('cycle')
                ->has('employee_id')
                ->has('employee')
                ->has('key_results.0', fn (Assert $kr) => $kr
                    ->has('id')
                    ->has('title')
                    ->has('target_value')
                    ->has('current_value')
                    ->has('unit')
                    ->has('progress')))
            ->has('cycles')
            ->has('employees')
            ->has('levels')
            ->has('statuses')
            ->has('perspectives')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->has('total_objectives')
                ->has('avg_progress')
                ->has('on_track')));
});

it('only lists objectives that belong to the current tenant', function (): void {
    makeObjective($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeObjective($otherTenant->id);

    $tenantTotal = Objective::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.okr'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('objectives', $tenantTotal));
});

it('creates an objective scoped to the current tenant', function (): void {
    $cycle = PerformanceCycle::factory()->create(['tenant_id' => $this->tenant->id]);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.okr.store'), [
            'title' => 'Luncurkan produk baru',
            'description' => 'Rilis MVP Q3',
            'level' => 'company',
            'status' => 'active',
            'cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
        ])
        ->assertRedirect(route('avana.okr'))
        ->assertSessionHas('success');

    $objective = Objective::where('title', 'Luncurkan produk baru')->firstOrFail();

    expect($objective->tenant_id)->toBe($this->tenant->id);
    expect($objective->level)->toBe('company');
    expect($objective->cycle_id)->toBe($cycle->id);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.okr.store'), [
            'title' => '',
            'level' => 'invalid',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['title', 'level', 'status']);
});

it('validates the objective against tenant-scoped cycles and employees', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignCycle = PerformanceCycle::factory()->create(['tenant_id' => $otherTenant->id]);

    actingAs($this->admin)
        ->post(route('avana.okr.store'), [
            'title' => 'Objektif',
            'level' => 'team',
            'status' => 'active',
            'cycle_id' => $foreignCycle->id,
            'employee_id' => 99999,
        ])
        ->assertSessionHasErrors(['cycle_id', 'employee_id']);
});

it('updates an existing objective', function (): void {
    $objective = makeObjective($this->tenant->id, ['status' => 'draft']);

    actingAs($this->admin)
        ->put(route('avana.okr.update', $objective), [
            'title' => 'Judul diperbarui',
            'description' => null,
            'level' => 'individual',
            'status' => 'done',
            'cycle_id' => null,
            'employee_id' => null,
        ])
        ->assertRedirect(route('avana.okr'))
        ->assertSessionHas('success');

    $objective->refresh();

    expect($objective->title)->toBe('Judul diperbarui');
    expect($objective->level)->toBe('individual');
    expect($objective->status)->toBe('done');
});

it('deletes an objective and cascades its key results', function (): void {
    $objective = makeObjective($this->tenant->id);
    $keyResult = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'KR',
        'target_value' => 100,
        'current_value' => 0,
        'progress' => 0,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.okr.destroy', $objective))
        ->assertSessionHas('success');

    expect(Objective::find($objective->id))->toBeNull();
    expect(KeyResult::find($keyResult->id))->toBeNull();
});

it('adds key results and rolls up the objective progress', function (): void {
    $objective = makeObjective($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.okr.kr.store', $objective), [
            'title' => 'KR setengah jalan',
            'target_value' => 100,
            'current_value' => 50,
            'unit' => '%',
        ])
        ->assertSessionHas('success');

    actingAs($this->admin)
        ->post(route('avana.okr.kr.store', $objective), [
            'title' => 'KR tuntas',
            'target_value' => 200,
            'current_value' => 200,
            'unit' => 'unit',
        ])
        ->assertSessionHas('success');

    $first = KeyResult::where('title', 'KR setengah jalan')->firstOrFail();
    $second = KeyResult::where('title', 'KR tuntas')->firstOrFail();

    expect($first->progress)->toBe(50);
    expect($second->progress)->toBe(100);

    $objective->refresh();

    // round((50 + 100) / 2) = 75
    expect($objective->progress)->toBe(75);
});

it('recomputes objective progress when a key result is updated', function (): void {
    $objective = makeObjective($this->tenant->id);
    $keyResult = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'KR',
        'target_value' => 100,
        'current_value' => 25,
        'progress' => 25,
    ]);
    $objective->update(['progress' => 25]);

    actingAs($this->admin)
        ->put(route('avana.okr.kr.update', $keyResult), [
            'title' => 'KR',
            'target_value' => 100,
            'current_value' => 90,
            'unit' => '%',
        ])
        ->assertSessionHas('success');

    $keyResult->refresh();
    $objective->refresh();

    expect($keyResult->progress)->toBe(90);
    expect($objective->progress)->toBe(90);
});

it('recomputes objective progress when a key result is deleted', function (): void {
    $objective = makeObjective($this->tenant->id);
    $keep = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'KR tetap',
        'target_value' => 100,
        'current_value' => 80,
        'progress' => 80,
    ]);
    $remove = KeyResult::create([
        'tenant_id' => $this->tenant->id,
        'objective_id' => $objective->id,
        'title' => 'KR hapus',
        'target_value' => 100,
        'current_value' => 20,
        'progress' => 20,
    ]);
    $objective->update(['progress' => 50]);

    actingAs($this->admin)
        ->delete(route('avana.okr.kr.destroy', $remove))
        ->assertSessionHas('success');

    expect(KeyResult::find($remove->id))->toBeNull();

    $objective->refresh();

    expect($objective->progress)->toBe(80);
    expect($keep->fresh())->not->toBeNull();
});

it('returns 404 when updating an objective from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeObjective($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.okr.update', $foreign), [
            'title' => 'Hack',
            'level' => 'team',
            'status' => 'active',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting an objective from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeObjective($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.okr.destroy', $foreign))
        ->assertNotFound();

    expect(Objective::find($foreign->id))->not->toBeNull();
});

it('returns 404 when adding a key result to an objective from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeObjective($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.okr.kr.store', $foreign), [
            'title' => 'KR',
            'target_value' => 100,
            'current_value' => 10,
        ])
        ->assertNotFound();
});

it('returns 404 when updating a key result from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeObjective($otherTenant->id);
    $keyResult = KeyResult::create([
        'tenant_id' => $otherTenant->id,
        'objective_id' => $foreign->id,
        'title' => 'KR',
        'target_value' => 100,
        'current_value' => 10,
        'progress' => 10,
    ]);

    actingAs($this->admin)
        ->put(route('avana.okr.kr.update', $keyResult), [
            'title' => 'KR',
            'target_value' => 100,
            'current_value' => 90,
        ])
        ->assertNotFound();
});

it('forbids a plain employee from listing or creating objectives', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.okr'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.okr.store'), [
            'title' => 'Tidak boleh',
            'level' => 'team',
            'status' => 'active',
        ])
        ->assertForbidden();
});

it('persists the balanced scorecard perspective', function (): void {
    actingAs($this->admin)
        ->post(route('avana.okr.store'), [
            'title' => 'Tingkatkan margin laba',
            'level' => 'company',
            'perspective' => 'financial',
            'status' => 'active',
        ])
        ->assertRedirect(route('avana.okr'))
        ->assertSessionHas('success');

    expect(Objective::where('title', 'Tingkatkan margin laba')->value('perspective'))->toBe('financial');
});

it('rejects an invalid balanced scorecard perspective', function (): void {
    actingAs($this->admin)
        ->post(route('avana.okr.store'), [
            'title' => 'Salah perspektif',
            'level' => 'company',
            'perspective' => 'bogus',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('perspective');
});
