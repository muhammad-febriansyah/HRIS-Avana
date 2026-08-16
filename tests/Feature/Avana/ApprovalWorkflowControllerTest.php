<?php

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Employee;
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
 * A minimal valid store/update payload for the acting tenant.
 */
function workflowPayload(int $tenantId, array $overrides = []): array
{
    $roleId = Role::forTenant($tenantId)->value('id');

    return array_merge([
        'name' => '',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
        'steps' => [
            ['approver_type' => 'direct_manager'],
            ['approver_type' => 'role', 'approver_role_id' => $roleId],
        ],
        'conditions' => [
            [
                'field' => 'days',
                'operator' => '>',
                'value' => '5',
                'extra_approver_type' => 'role',
                'extra_approver_ref' => $roleId,
            ],
        ],
    ], $overrides);
}

it('renders the approval-workflow index with the wizard props', function (): void {
    ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.approval-workflow'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/approval-workflow/index', false)
            ->has('workflows.0', fn (Assert $row) => $row
                ->has('id')
                ->has('module_label')
                ->has('approval_mode_label')
                ->has('step_count')
                ->has('is_active')
                ->has('steps')
                ->etc())
            ->has('modules')
            ->has('approverTypes')
            ->has('options.roles')
            ->has('kpis.total'));
});

it('stores a workflow with ordered steps and conditions', function (): void {
    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertRedirect(route('avana.approval-workflow'));

    $workflow = ApprovalWorkflow::forTenant($this->tenant->id)->latest('id')->firstOrFail();

    expect($workflow->name)->toBe('Cuti (Leave)') // defaults to module label
        ->and($workflow->request_type)->toBe('leave')
        ->and($workflow->approval_mode)->toBe('sequential')
        ->and($workflow->conditions)->toHaveCount(1);

    $steps = ApprovalStep::where('approval_workflow_id', $workflow->id)->orderBy('step_order')->get();

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->step_order)->toBe(1)
        ->and($steps[0]->approver_type)->toBe('direct_manager')
        ->and($steps[0]->approver_role_id)->toBeNull()
        ->and($steps[1]->approver_type)->toBe('role')
        ->and($steps[1]->approver_role_id)->not->toBeNull();
});

it('keeps only the approver reference matching the step type', function (): void {
    $roleId = Role::forTenant($this->tenant->id)->value('id');

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id, [
            'steps' => [
                // role id supplied but type is direct_manager — must be dropped
                ['approver_type' => 'direct_manager', 'approver_role_id' => $roleId],
            ],
            'conditions' => [],
        ]))
        ->assertRedirect();

    $step = ApprovalStep::forTenant($this->tenant->id)->latest('id')->firstOrFail();

    expect($step->approver_role_id)->toBeNull();
});

it('updates a workflow and replaces its steps', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    actingAs($this->admin)
        ->put(route('avana.approval-workflow.update', $workflow), workflowPayload($this->tenant->id, [
            'approval_mode' => 'parallel',
            'conditions' => [],
        ]))
        ->assertRedirect(route('avana.approval-workflow'));

    $workflow->refresh();

    expect($workflow->approval_mode)->toBe('parallel')
        ->and($workflow->steps()->count())->toBe(2);
});

it('toggles the active flag', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.toggle', $workflow))
        ->assertRedirect();

    expect($workflow->fresh()->is_active)->toBeFalse();
});

it('soft-deletes a workflow', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.approval-workflow.destroy', $workflow))
        ->assertRedirect();

    expect(ApprovalWorkflow::find($workflow->id))->toBeNull()
        ->and(ApprovalWorkflow::withTrashed()->find($workflow->id))->not->toBeNull();
});

it('rejects an unknown request_type', function (): void {
    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id, [
            'request_type' => 'nonexistent',
        ]))
        ->assertSessionHasErrors('request_type');
});

it('cannot update a workflow from another tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-wf']);
    $foreign = ApprovalWorkflow::create([
        'tenant_id' => $other->id,
        'name' => 'Asing',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.approval-workflow.update', $foreign), workflowPayload($this->tenant->id))
        ->assertNotFound();
});

it('forbids a non-privileged user', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $plainUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $plainUser->roles()->sync([$employeeRole->id]);

    actingAs($plainUser)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertForbidden();
});

it('refuses a second workflow for a module that already has one', function (): void {
    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertRedirect(route('avana.approval-workflow'));

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertSessionHasErrors('request_type');

    expect(ApprovalWorkflow::forTenant($this->tenant->id)->where('request_type', 'leave')->count())
        ->toBe(1);
});

it('allows a division-scoped workflow beside the tenant-wide default', function (): void {
    $department = Department::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertSessionHasNoErrors();

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id, [
            'department_id' => $department->id,
        ]))
        ->assertSessionHasNoErrors();

    $scoped = ApprovalWorkflow::forTenant($this->tenant->id)
        ->where('request_type', 'leave')
        ->where('department_id', $department->id)
        ->firstOrFail();

    // The auto-composed name carries the division so the list reads clearly.
    expect($scoped->name)->toContain($department->name);
    expect(ApprovalWorkflow::forTenant($this->tenant->id)->where('request_type', 'leave')->count())
        ->toBe(2);
});

it('refuses a second workflow for the same module and division', function (): void {
    $department = Department::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id, [
            'department_id' => $department->id,
        ]))
        ->assertSessionHasNoErrors();

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id, [
            'department_id' => $department->id,
        ]))
        ->assertSessionHasErrors('request_type');
});

it('lets another tenant configure the same module', function (): void {
    $other = Tenant::create(['name' => 'PT Seberang', 'slug' => 'pt-seberang-wf']);
    ApprovalWorkflow::create([
        'tenant_id' => $other->id,
        'name' => 'Cuti Tenant Lain',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertSessionHasNoErrors();

    expect(ApprovalWorkflow::forTenant($this->tenant->id)->where('request_type', 'leave')->count())
        ->toBe(1);
});

it('lets a workflow keep its own module when edited', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.approval-workflow.update', $workflow), workflowPayload($this->tenant->id, [
            'name' => 'Persetujuan Cuti (revisi)',
        ]))
        ->assertSessionHasNoErrors();

    expect($workflow->fresh()->name)->toBe('Persetujuan Cuti (revisi)');
});

it('frees the module again once its workflow is deleted', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.approval-workflow.destroy', $workflow))
        ->assertRedirect();

    // A soft-deleted flow must not keep the module hostage.
    actingAs($this->admin)
        ->post(route('avana.approval-workflow.store'), workflowPayload($this->tenant->id))
        ->assertSessionHasNoErrors();
});

it('tells the wizard which modules are already taken', function (): void {
    ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->get(route('avana.approval-workflow'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workflows.0.request_type', 'leave'));
});

it('labels a named approver with the employee, not whoever owns that user id', function (): void {
    // `approver_user_id` holds an EMPLOYEE id. The preview used to read it as a
    // user id, so a step aimed at employee 34 was announced under the name of
    // user 34 — a different person entirely whenever the two sequences drifted.
    $employee = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->whereColumn('id', '!=', 'user_id')
        ->firstOrFail();

    $decoy = User::find($employee->getKey());

    expect($decoy?->name)->not->toBe($employee->full_name);

    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Persetujuan Cuti',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    $workflow->steps()->create([
        'tenant_id' => $this->tenant->id,
        'step_order' => 1,
        'approver_type' => 'specific_user',
        'approver_user_id' => $employee->getKey(),
    ]);

    actingAs($this->admin)
        ->get(route('avana.approval-workflow'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workflows.0.steps.0.approver_label', $employee->full_name));
});
