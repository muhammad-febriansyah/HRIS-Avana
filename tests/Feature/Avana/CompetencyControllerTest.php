<?php

use App\Models\Competency;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
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
 * Create a competency for the given tenant.
 */
function makeCompetency(int $tenantId, array $overrides = []): Competency
{
    return Competency::create(array_merge([
        'tenant_id' => $tenantId,
        'name' => 'Kompetensi '.fake()->unique()->word(),
        'category' => 'Teknis',
    ], $overrides));
}

it('renders the competency index with the expected props', function (): void {
    $competency = makeCompetency($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    EmployeeCompetency::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'competency_id' => $competency->id,
        'level' => 4,
    ]);

    actingAs($this->admin)
        ->get(route('avana.kompetensi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kompetensi/index', false)
            ->has('competencies.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('category')
                ->has('description'))
            ->has('employees')
            ->where('matrix.'.$employee->id.'-'.$competency->id, 4)
            ->where('kpis.average_level', 4)
            ->has('kpis.total_competencies'));
});

it('only lists competencies that belong to the current tenant', function (): void {
    makeCompetency($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeCompetency($otherTenant->id);

    $tenantTotal = Competency::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.kompetensi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('competencies', $tenantTotal));
});

it('stores a competency scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kompetensi.store'), [
            'name' => 'Kepemimpinan',
            'category' => 'Soft Skill',
            'description' => 'Kemampuan memimpin tim',
        ])
        ->assertRedirect(route('avana.kompetensi'))
        ->assertSessionHas('success');

    $competency = Competency::where('name', 'Kepemimpinan')->firstOrFail();

    expect($competency->tenant_id)->toBe($this->tenant->id);
    expect($competency->category)->toBe('Soft Skill');
});

it('validates the competency request', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kompetensi.store'), ['name' => ''])
        ->assertSessionHasErrors(['name']);
});

it('updates a competency', function (): void {
    $competency = makeCompetency($this->tenant->id, ['name' => 'Old']);

    actingAs($this->admin)
        ->put(route('avana.kompetensi.update', $competency), [
            'name' => 'New',
            'category' => 'Manajerial',
            'description' => null,
        ])
        ->assertRedirect(route('avana.kompetensi'))
        ->assertSessionHas('success');

    $competency->refresh();

    expect($competency->name)->toBe('New');
    expect($competency->category)->toBe('Manajerial');
});

it('deletes a competency', function (): void {
    $competency = makeCompetency($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.kompetensi.destroy', $competency))
        ->assertSessionHas('success');

    expect(Competency::find($competency->id))->toBeNull();
});

it('records an assessment and upserts on re-assess', function (): void {
    $competency = makeCompetency($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kompetensi.assess'), [
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'level' => 3,
            'assessed_at' => '2026-07-01',
        ])
        ->assertSessionHas('success');

    $record = EmployeeCompetency::where('employee_id', $employee->id)
        ->where('competency_id', $competency->id)
        ->firstOrFail();

    expect($record->level)->toBe(3);
    expect($record->tenant_id)->toBe($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kompetensi.assess'), [
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'level' => 5,
        ])
        ->assertSessionHas('success');

    expect(EmployeeCompetency::where('employee_id', $employee->id)
        ->where('competency_id', $competency->id)
        ->count())->toBe(1);
    expect(EmployeeCompetency::where('employee_id', $employee->id)
        ->where('competency_id', $competency->id)
        ->first()->level)->toBe(5);
});

it('validates the assess request and rejects out-of-range levels', function (): void {
    $competency = makeCompetency($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kompetensi.assess'), [
            'employee_id' => $employee->id,
            'competency_id' => $competency->id,
            'level' => 9,
        ])
        ->assertSessionHasErrors(['level']);
});

it('returns 404 when updating a competency from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeCompetency($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.kompetensi.update', $foreign), ['name' => 'Hack'])
        ->assertNotFound();
});

it('forbids a plain employee from managing competencies', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.kompetensi'))
        ->assertForbidden();
});
