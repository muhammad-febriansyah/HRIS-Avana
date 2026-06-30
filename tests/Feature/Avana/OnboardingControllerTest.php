<?php

use App\Models\Employee;
use App\Models\OnboardingProgram;
use App\Models\OnboardingTask;
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
 * Create an employee under the given tenant.
 */
function makeOnboardingEmployee(int $tenantId, array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
        'full_name' => fake()->name(),
    ], $overrides));
}

/**
 * Create an onboarding program for the given tenant.
 */
function makeOnboardingProgram(int $tenantId, array $overrides = []): OnboardingProgram
{
    $employeeId = $overrides['employee_id'] ?? makeOnboardingEmployee($tenantId)->id;

    return OnboardingProgram::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'start_date' => '2026-07-01',
        'status' => 'in_progress',
    ], $overrides));
}

it('renders the onboarding index with the expected props', function (): void {
    $program = makeOnboardingProgram($this->tenant->id);
    OnboardingTask::create([
        'tenant_id' => $this->tenant->id,
        'onboarding_program_id' => $program->id,
        'title' => 'Tanda tangan kontrak',
        'category' => 'Dokumen',
        'is_done' => false,
    ]);

    actingAs($this->admin)
        ->get(route('avana.onboarding'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/onboarding/index', false)
            ->has('programs.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee_id')
                ->has('employee')
                ->has('start_date')
                ->has('status')
                ->has('tasks')
                ->has('tasks_total')
                ->has('tasks_done'))
            ->has('employees')
            ->has('kpis'));
});

it('only lists programs that belong to the current tenant', function (): void {
    makeOnboardingProgram($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeOnboardingProgram($otherTenant->id);

    $tenantTotal = OnboardingProgram::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.onboarding'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('programs', $tenantTotal));
});

it('creates an onboarding program scoped to the current tenant', function (): void {
    $employee = makeOnboardingEmployee($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.onboarding.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-05',
        ])
        ->assertRedirect(route('avana.onboarding'))
        ->assertSessionHas('success');

    $program = OnboardingProgram::where('employee_id', $employee->id)->firstOrFail();

    expect($program->tenant_id)->toBe($this->tenant->id);
    expect($program->status)->toBe('in_progress');
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.onboarding.store'), [
            'employee_id' => 99999,
        ])
        ->assertSessionHasErrors(['employee_id']);
});

it('adds a task to a program', function (): void {
    $program = makeOnboardingProgram($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.onboarding.task.store', $program), [
            'title' => 'Setup email perusahaan',
            'category' => 'IT',
            'due_date' => '2026-07-10',
        ])
        ->assertSessionHas('success');

    $task = OnboardingTask::where('onboarding_program_id', $program->id)->firstOrFail();

    expect($task->tenant_id)->toBe($this->tenant->id);
    expect($task->title)->toBe('Setup email perusahaan');
    expect($task->is_done)->toBeFalse();
});

it('toggles a task and recomputes the program status', function (): void {
    $program = makeOnboardingProgram($this->tenant->id, ['status' => 'in_progress']);
    $task = OnboardingTask::create([
        'tenant_id' => $this->tenant->id,
        'onboarding_program_id' => $program->id,
        'title' => 'Orientasi',
        'is_done' => false,
    ]);

    actingAs($this->admin)
        ->post(route('avana.onboarding.task.toggle', $task))
        ->assertSessionHas('success');

    $task->refresh();
    $program->refresh();

    expect($task->is_done)->toBeTrue();
    expect($program->status)->toBe('completed');

    actingAs($this->admin)
        ->post(route('avana.onboarding.task.toggle', $task))
        ->assertSessionHas('success');

    $program->refresh();
    expect($program->status)->toBe('in_progress');
});

it('deletes a program with its tasks', function (): void {
    $program = makeOnboardingProgram($this->tenant->id);
    OnboardingTask::create([
        'tenant_id' => $this->tenant->id,
        'onboarding_program_id' => $program->id,
        'title' => 'Tugas',
        'is_done' => false,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.onboarding.destroy', $program))
        ->assertSessionHas('success');

    expect(OnboardingProgram::find($program->id))->toBeNull();
    expect(OnboardingTask::where('onboarding_program_id', $program->id)->count())->toBe(0);
});

it('returns 404 when adding a task to a program from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeOnboardingProgram($otherTenant->id);

    actingAs($this->admin)
        ->post(route('avana.onboarding.task.store', $foreign), [
            'title' => 'Hack',
        ])
        ->assertNotFound();
});

it('returns 404 when toggling a task from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeOnboardingProgram($otherTenant->id);
    $task = OnboardingTask::create([
        'tenant_id' => $otherTenant->id,
        'onboarding_program_id' => $foreign->id,
        'title' => 'Tugas',
        'is_done' => false,
    ]);

    actingAs($this->admin)
        ->post(route('avana.onboarding.task.toggle', $task))
        ->assertNotFound();
});

it('returns 404 when deleting a program from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeOnboardingProgram($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.onboarding.destroy', $foreign))
        ->assertNotFound();

    expect(OnboardingProgram::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from managing onboarding', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.onboarding'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.onboarding.store'), [
            'employee_id' => makeOnboardingEmployee($this->tenant->id)->id,
        ])
        ->assertForbidden();
});
