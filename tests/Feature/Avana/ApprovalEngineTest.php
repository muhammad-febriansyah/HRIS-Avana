<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApprovalEngine;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Three distinct employees: the requester, their manager (step 1), and a
    // designated approver (step 2). The requester keeps a real leave balance.
    $employees = Employee::forTenant($this->tenant->id)->orderBy('id')->take(3)->get();
    $this->subject = $employees[0];
    $this->manager = $employees[1];
    $this->approver = $employees[2];

    $this->subject->update(['manager_id' => $this->manager->id]);

    $this->leaveType = LeaveType::forTenant($this->tenant->id)->firstOrFail();
});

/**
 * Build a two-step leave workflow: direct manager, then a specific employee.
 */
function twoStepLeaveWorkflow(int $tenantId, int $specificApproverEmployeeId): ApprovalWorkflow
{
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $tenantId,
        'name' => 'Cuti 2 Level',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    ApprovalStep::create([
        'tenant_id' => $tenantId,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);
    ApprovalStep::create([
        'tenant_id' => $tenantId,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 2,
        'approver_type' => 'specific_user',
        'approver_user_id' => $specificApproverEmployeeId,
    ]);

    return $workflow;
}

it('routes a submitted leave to the first workflow step', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    actingAs($this->admin)
        ->post(route('avana.cuti.store'), [
            'employee_id' => $this->subject->id,
            'leave_type_id' => $this->leaveType->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-12',
            'reason' => 'Cuti',
        ])
        ->assertRedirect(route('avana.cuti'));

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    // Pending, routed to step 1's approver (the direct manager), and tracked.
    expect($leave->status)->toBe('pending');
    expect((int) $leave->current_approver_id)->toBe($this->manager->id);

    $instance = ApprovalRequest::where('approvable_type', LeaveRequest::class)
        ->where('approvable_id', $leave->id)
        ->firstOrFail();
    expect((int) $instance->current_step)->toBe(1);
    expect($instance->status)->toBe('pending');
});

it('advances to the next step on approval and finalizes on the last', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    $balance = LeaveBalance::query()
        ->where('employee_id', $this->subject->id)
        ->where('leave_type_id', $this->leaveType->id)
        ->where('year', 2026)
        ->firstOrFail();
    $usedBefore = (float) $balance->used;

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    // First approval: advance to step 2, re-route, still pending, no balance draw.
    actingAs($this->admin)->post(route('avana.cuti.approve', $leave))->assertSessionHas('success');

    $leave->refresh();
    expect($leave->status)->toBe('pending');
    expect((int) $leave->current_approver_id)->toBe($this->approver->id);

    $instance = ApprovalRequest::where('approvable_id', $leave->id)
        ->where('approvable_type', LeaveRequest::class)->firstOrFail();
    expect((int) $instance->current_step)->toBe(2);

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore);

    // Final approval: finalize the leave with its full side effects.
    actingAs($this->admin)->post(route('avana.cuti.approve', $leave))->assertSessionHas('success');

    $leave->refresh();
    $instance->refresh();
    expect($leave->status)->toBe('approved');
    expect($instance->status)->toBe('approved');

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore + 3);
});

it('rejects at any step and terminates the workflow instance', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    actingAs($this->admin)->post(route('avana.cuti.reject', $leave))->assertSessionHas('success');

    $leave->refresh();
    $instance = ApprovalRequest::where('approvable_id', $leave->id)
        ->where('approvable_type', LeaveRequest::class)->firstOrFail();

    expect($leave->status)->toBe('rejected');
    expect($instance->status)->toBe('rejected');
});

it('surfaces a role-group step to every holder of the role, not one representative', function (): void {
    // Give the "approver" employee a login whose user holds a board role.
    $role = Role::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Dewan Direksi',
        'code' => 'dewan_direksi',
    ]);
    $holderUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $holderUser->roles()->attach($role->id);
    $this->approver->update(['user_id' => $holderUser->id]);

    // A single-step workflow whose approver is that role (a group step).
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti Dewan',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'role',
        'approver_role_id' => $role->id,
    ]);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    // A group step has no single owner.
    expect($leave->current_approver_id)->toBeNull();

    // The request surfaces to any holder of the role, but not to a non-holder.
    expect(ApprovalEngine::pendingApprovableIdsFor(LeaveRequest::class, $this->approver->fresh()))
        ->toContain($leave->id);
    expect(ApprovalEngine::pendingApprovableIdsFor(LeaveRequest::class, $this->manager->fresh()))
        ->not->toContain($leave->id);
});

it('keeps the legacy manager routing when no workflow is active', function (): void {
    // No workflow created — the request must fall back to manager_id routing and
    // create no workflow instance.
    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    expect((int) $leave->current_approver_id)->toBe($this->manager->id);
    expect(ApprovalRequest::where('approvable_id', $leave->id)
        ->where('approvable_type', LeaveRequest::class)->exists())->toBeFalse();
});
