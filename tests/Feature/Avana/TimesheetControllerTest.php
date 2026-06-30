<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Timesheet;
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
 * Create a minimal active employee for the given tenant.
 */
function makeTsEmployee(int $tenantId, array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ], $overrides));
}

/**
 * Create a project for the given tenant.
 */
function makeProject(int $tenantId, array $overrides = []): Project
{
    return Project::create(array_merge([
        'tenant_id' => $tenantId,
        'name' => 'Proyek '.fake()->unique()->word(),
        'code' => 'PRJ-'.fake()->unique()->numerify('###'),
        'status' => 'active',
    ], $overrides));
}

/**
 * Create a timesheet entry for the given tenant.
 */
function makeTimesheet(int $tenantId, array $overrides = []): Timesheet
{
    $employeeId = $overrides['employee_id'] ?? (Employee::forTenant($tenantId)->value('id') ?? makeTsEmployee($tenantId)->id);
    $projectId = $overrides['project_id'] ?? makeProject($tenantId)->id;

    return Timesheet::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'project_id' => $projectId,
        'date' => '2026-07-01',
        'hours' => 8,
        'task' => 'Develop feature',
        'notes' => null,
    ], $overrides));
}

it('renders the timesheet index with the expected props', function (): void {
    makeTimesheet($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.timesheet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/timesheet/index', false)
            ->has('entries.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee')
                ->has('employee_id')
                ->has('project')
                ->has('project_id')
                ->has('date')
                ->has('hours')
                ->has('task')
                ->has('notes'))
            ->has('projects.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('code')
                ->has('status')
                ->has('timesheets_count'))
            ->has('employees')
            ->has('filters')
            ->has('kpis'));
});

it('only lists timesheet entries that belong to the current tenant', function (): void {
    makeTimesheet($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeTimesheet($otherTenant->id, ['employee_id' => makeTsEmployee($otherTenant->id)->id]);

    $tenantTotal = Timesheet::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.timesheet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('entries', $tenantTotal));
});

it('creates a project scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.timesheet.project.store'), [
            'name' => 'Aplikasi Mobile',
            'code' => 'MOB-01',
            'status' => 'active',
        ])
        ->assertRedirect(route('avana.timesheet'))
        ->assertSessionHas('success');

    $project = Project::where('name', 'Aplikasi Mobile')->firstOrFail();

    expect($project->tenant_id)->toBe($this->tenant->id);
    expect($project->status)->toBe('active');
});

it('validates required fields on project store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.timesheet.project.store'), [
            'name' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'status']);
});

it('logs a timesheet entry scoped to the current tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $project = makeProject($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.timesheet.store'), [
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'date' => '2026-07-02',
            'hours' => 6.5,
            'task' => 'Bug fixing',
        ])
        ->assertRedirect(route('avana.timesheet'))
        ->assertSessionHas('success');

    $entry = Timesheet::where('task', 'Bug fixing')->firstOrFail();

    expect($entry->tenant_id)->toBe($this->tenant->id);
    expect($entry->employee_id)->toBe($employee->id);
    expect($entry->project_id)->toBe($project->id);
    expect((float) $entry->hours)->toBe(6.5);
});

it('validates required fields on timesheet store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.timesheet.store'), [
            'employee_id' => 99999,
            'project_id' => 99999,
            'date' => '',
            'hours' => 0,
        ])
        ->assertSessionHasErrors(['employee_id', 'project_id', 'date', 'hours']);
});

it('deletes a timesheet entry', function (): void {
    $entry = makeTimesheet($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.timesheet.destroy', $entry))
        ->assertSessionHas('success');

    expect(Timesheet::find($entry->id))->toBeNull();
});

it('returns 404 when deleting a timesheet entry from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeTimesheet($otherTenant->id, ['employee_id' => makeTsEmployee($otherTenant->id)->id]);

    actingAs($this->admin)
        ->delete(route('avana.timesheet.destroy', $foreign))
        ->assertNotFound();

    expect(Timesheet::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from listing timesheets', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.timesheet'))
        ->assertForbidden();
});
