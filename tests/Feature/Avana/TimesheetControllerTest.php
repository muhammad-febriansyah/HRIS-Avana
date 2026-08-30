<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
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

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
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
        'is_billable' => true,
        'default_bill_rate' => 200_000,
        'default_cost_rate' => 80_000,
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
        'date' => now()->startOfMonth()->toDateString(),
        'hours' => 8,
        'task' => 'Develop feature',
        'notes' => null,
        'status' => Timesheet::STATUS_APPROVED,
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
                ->has('notes')
                ->has('status')
                ->has('status_label')
                ->has('is_billable')
                ->has('bill_rate')
                ->has('cost_rate')
                ->has('bill_amount')
                ->has('cost_amount')
                ->has('source')
                ->has('approved_by')
                ->has('approved_at')
                ->has('rejection_reason'))
            ->has('projects.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('code')
                ->has('client_name')
                ->has('description')
                ->has('status')
                ->has('manager_id')
                ->has('manager')
                ->has('start_date')
                ->has('end_date')
                ->has('budget_amount')
                ->has('budget_hours')
                ->has('is_billable')
                ->has('default_bill_rate')
                ->has('default_cost_rate')
                ->has('timesheets_count')
                ->has('members'))
            ->has('employees')
            ->has('filters')
            ->has('kpis')
            ->has('report.projects')
            ->has('report.employees')
            ->has('report.totals')
            ->has('can'));
});

it('only lists timesheet entries that belong to the current tenant', function (): void {
    makeTimesheet($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeTimesheet($otherTenant->id, ['employee_id' => makeTsEmployee($otherTenant->id)->id]);

    actingAs($this->admin)
        ->get(route('avana.timesheet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('entries', 1));
});

it('creates a project scoped to the current tenant with its members', function (): void {
    $lead = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.timesheet.project.store'), [
            'name' => 'Aplikasi Mobile',
            'code' => 'MOB-01',
            'client_name' => 'PT Klien Jaya',
            'status' => 'active',
            'is_billable' => true,
            'default_bill_rate' => 250_000,
            'members' => [
                ['employee_id' => $lead->id, 'bill_rate' => 300_000, 'cost_rate' => 100_000],
            ],
        ])
        ->assertRedirect(route('avana.timesheet'))
        ->assertSessionHas('success');

    $project = Project::where('name', 'Aplikasi Mobile')->firstOrFail();

    expect($project->tenant_id)->toBe($this->tenant->id)
        ->and($project->status)->toBe('active')
        ->and((float) $project->default_bill_rate)->toBe(250000.0)
        ->and($project->members)->toHaveCount(1)
        ->and((float) $project->members->first()->bill_rate)->toBe(300000.0);
});

it('validates required fields on project store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.timesheet.project.store'), [
            'name' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'status', 'is_billable']);
});

it('updates a project and replaces its member rows', function (): void {
    $project = makeProject($this->tenant->id);
    $first = Employee::forTenant($this->tenant->id)->firstOrFail();
    $second = makeTsEmployee($this->tenant->id);

    ProjectMember::create([
        'tenant_id' => $this->tenant->id,
        'project_id' => $project->id,
        'employee_id' => $first->id,
    ]);

    actingAs($this->admin)
        ->put(route('avana.timesheet.project.update', $project), [
            'name' => 'Nama Baru',
            'status' => 'archived',
            'is_billable' => false,
            'members' => [['employee_id' => $second->id]],
        ])
        ->assertRedirect(route('avana.timesheet'))
        ->assertSessionHas('success');

    $project->refresh()->load('members');

    expect($project->name)->toBe('Nama Baru')
        ->and($project->status)->toBe('archived')
        ->and($project->is_billable)->toBeFalse()
        ->and($project->members->pluck('employee_id')->all())->toBe([$second->id]);
});

it('refuses to delete a project that still has entries', function (): void {
    $entry = makeTimesheet($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.timesheet.project.destroy', $entry->project_id))
        ->assertSessionHas('error');

    expect(Project::find($entry->project_id))->not->toBeNull();
});

it('logs a timesheet entry as approved and priced', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $project = makeProject($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.timesheet.store'), [
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'date' => now()->toDateString(),
            'hours' => 6.5,
            'task' => 'Bug fixing',
            'is_billable' => true,
        ])
        ->assertRedirect(route('avana.timesheet'))
        ->assertSessionHas('success');

    $entry = Timesheet::where('task', 'Bug fixing')->firstOrFail();

    expect($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->employee_id)->toBe($employee->id)
        ->and($entry->status)->toBe(Timesheet::STATUS_APPROVED)
        ->and((float) $entry->hours)->toBe(6.5)
        // 200.000 sell rate × 6,5 hours, frozen onto the row.
        ->and((float) $entry->bill_amount)->toBe(1_300_000.0)
        ->and((float) $entry->cost_amount)->toBe(520_000.0);
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

it('refuses an entry that pushes the day past 24 hours', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $project = makeProject($this->tenant->id);

    makeTimesheet($this->tenant->id, [
        'employee_id' => $employee->id,
        'project_id' => $project->id,
        'date' => '2026-08-15',
        'hours' => 20,
    ]);

    actingAs($this->admin)
        ->post(route('avana.timesheet.store'), [
            'employee_id' => $employee->id,
            'project_id' => $project->id,
            'date' => '2026-08-15',
            'hours' => 6,
        ])
        ->assertSessionHasErrors('hours');
});

it('approves pending entries and prices them', function (): void {
    $project = makeProject($this->tenant->id);
    $entry = makeTimesheet($this->tenant->id, [
        'project_id' => $project->id,
        'hours' => 4,
        'status' => Timesheet::STATUS_PENDING,
        'source' => 'mobile',
    ]);

    actingAs($this->admin)
        ->post(route('avana.timesheet.approve'), ['ids' => [$entry->id]])
        ->assertSessionHas('success');

    $entry->refresh();

    expect($entry->status)->toBe(Timesheet::STATUS_APPROVED)
        ->and($entry->approved_by)->toBe($this->admin->id)
        ->and((float) $entry->bill_amount)->toBe(800_000.0);
});

it('rejects pending entries with a reason and leaves them unpriced', function (): void {
    $entry = makeTimesheet($this->tenant->id, [
        'hours' => 4,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    actingAs($this->admin)
        ->post(route('avana.timesheet.reject'), [
            'ids' => [$entry->id],
            'reason' => 'Jam tidak sesuai laporan harian',
        ])
        ->assertSessionHas('success');

    $entry->refresh();

    expect($entry->status)->toBe(Timesheet::STATUS_REJECTED)
        ->and($entry->rejection_reason)->toBe('Jam tidak sesuai laporan harian')
        ->and((float) $entry->bill_amount)->toBe(0.0);
});

it('reports profitability from approved entries only', function (): void {
    $project = makeProject($this->tenant->id);

    makeTimesheet($this->tenant->id, [
        'project_id' => $project->id,
        'hours' => 5,
        'bill_amount' => 1_000_000,
        'cost_amount' => 400_000,
    ]);

    makeTimesheet($this->tenant->id, [
        'project_id' => $project->id,
        'hours' => 9,
        'status' => Timesheet::STATUS_PENDING,
    ]);

    actingAs($this->admin)
        ->get(route('avana.timesheet'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.totals.hours', 5)
            ->where('report.totals.bill_amount', 1000000)
            ->where('report.totals.margin', 600000)
            ->has('report.projects', 1));
});

it('exports the filtered entries as csv', function (): void {
    makeTimesheet($this->tenant->id);

    $response = actingAs($this->admin)->get(route('avana.timesheet.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
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
