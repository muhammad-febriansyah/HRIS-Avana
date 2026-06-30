<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\TalentAssessment;
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
 * Create a talent assessment for an employee under the given tenant.
 */
function makeTalentAssessment(int $tenantId, array $overrides = []): TalentAssessment
{
    $employeeId = $overrides['employee_id'] ?? Employee::create([
        'tenant_id' => $tenantId,
        'full_name' => fake()->name(),
        'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
    ])->id;

    return TalentAssessment::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'performance_level' => 'medium',
        'potential_level' => 'medium',
    ], $overrides));
}

it('renders the talent index with the expected props', function (): void {
    makeTalentAssessment($this->tenant->id, [
        'performance_level' => 'high',
        'potential_level' => 'high',
        'successor_for' => 'Manajer Operasional',
    ]);

    actingAs($this->admin)
        ->get(route('avana.talenta'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/talenta/index', false)
            ->has('assessments.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee_id')
                ->has('employee')
                ->has('employee_number')
                ->has('performance_level')
                ->has('potential_level')
                ->has('note')
                ->has('successor_for'))
            ->has('successors.0')
            ->has('employees')
            ->has('levels')
            ->where('kpis.stars', 1));
});

it('creates an assessment scoped to the current tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.talenta.store'), [
            'employee_id' => $employee->id,
            'performance_level' => 'high',
            'potential_level' => 'low',
            'note' => 'Spesialis teknis',
            'successor_for' => null,
        ])
        ->assertRedirect(route('avana.talenta'))
        ->assertSessionHas('success');

    $assessment = TalentAssessment::where('employee_id', $employee->id)->firstOrFail();

    expect($assessment->tenant_id)->toBe($this->tenant->id);
    expect($assessment->performance_level)->toBe('high');
    expect($assessment->potential_level)->toBe('low');
});

it('upserts the assessment for the same employee', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.talenta.store'), [
            'employee_id' => $employee->id,
            'performance_level' => 'low',
            'potential_level' => 'low',
        ])
        ->assertSessionHas('success');

    actingAs($this->admin)
        ->post(route('avana.talenta.store'), [
            'employee_id' => $employee->id,
            'performance_level' => 'high',
            'potential_level' => 'high',
        ])
        ->assertSessionHas('success');

    expect(TalentAssessment::where('employee_id', $employee->id)->count())->toBe(1);
    expect(TalentAssessment::where('employee_id', $employee->id)->first()->performance_level)->toBe('high');
});

it('validates the assessment request', function (): void {
    actingAs($this->admin)
        ->post(route('avana.talenta.store'), [
            'employee_id' => 99999,
            'performance_level' => 'invalid',
            'potential_level' => 'invalid',
        ])
        ->assertSessionHasErrors(['employee_id', 'performance_level', 'potential_level']);
});

it('updates an existing assessment', function (): void {
    $assessment = makeTalentAssessment($this->tenant->id, ['performance_level' => 'low']);

    actingAs($this->admin)
        ->put(route('avana.talenta.update', $assessment), [
            'performance_level' => 'high',
            'potential_level' => 'medium',
            'note' => 'Naik kelas',
            'successor_for' => 'Kepala Cabang',
        ])
        ->assertRedirect(route('avana.talenta'))
        ->assertSessionHas('success');

    $assessment->refresh();

    expect($assessment->performance_level)->toBe('high');
    expect($assessment->successor_for)->toBe('Kepala Cabang');
});

it('deletes an assessment', function (): void {
    $assessment = makeTalentAssessment($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.talenta.destroy', $assessment))
        ->assertSessionHas('success');

    expect(TalentAssessment::find($assessment->id))->toBeNull();
});

it('returns 404 when updating an assessment from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeTalentAssessment($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.talenta.update', $foreign), [
            'performance_level' => 'high',
            'potential_level' => 'high',
        ])
        ->assertNotFound();
});

it('forbids a plain employee from accessing talent management', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.talenta'))
        ->assertForbidden();
});
