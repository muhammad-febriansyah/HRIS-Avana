<?php

use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApprovalEngine;
use Database\Seeders\AvanaDemoSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

it('prefers the requester division\'s own workflow over the tenant default', function (): void {
    $department = App\Models\Department::forTenant($this->tenant->id)->firstOrFail();
    $this->subject->update(['department_id' => $department->id]);

    // Tenant-wide default: two steps starting at the direct manager.
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    // The division's own flow: a single specific approver.
    $scoped = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti — '.$department->name,
        'request_type' => 'leave',
        'department_id' => $department->id,
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $scoped->id,
        'step_order' => 1,
        'approver_type' => 'specific_user',
        'approver_user_id' => $this->approver->id,
    ]);

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
    $instance = ApprovalRequest::where('approvable_type', LeaveRequest::class)
        ->where('approvable_id', $leave->id)
        ->firstOrFail();

    // Routed by the scoped flow: straight to the named approver, not the manager.
    expect((int) $instance->approval_workflow_id)->toBe($scoped->id);
    expect((int) $leave->current_approver_id)->toBe($this->approver->id);
});

it('falls back to the tenant default for a division without its own workflow', function (): void {
    $departments = App\Models\Department::forTenant($this->tenant->id)->orderBy('id')->take(2)->get();
    expect($departments)->toHaveCount(2);

    // Scoped flow belongs to the OTHER division; the requester sits elsewhere.
    $this->subject->update(['department_id' => $departments[0]->id]);
    $default = twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    $scoped = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti — '.$departments[1]->name,
        'request_type' => 'leave',
        'department_id' => $departments[1]->id,
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $scoped->id,
        'step_order' => 1,
        'approver_type' => 'specific_user',
        'approver_user_id' => $this->approver->id,
    ]);

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
    $instance = ApprovalRequest::where('approvable_type', LeaveRequest::class)
        ->where('approvable_id', $leave->id)
        ->firstOrFail();

    expect((int) $instance->approval_workflow_id)->toBe($default->id);
    expect((int) $leave->current_approver_id)->toBe($this->manager->id);
});

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

it('appends a conditional extra approver when the condition matches', function (): void {
    // Base: direct manager. Condition: leave longer than 2 days needs the
    // designated employee too.
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti Bersyarat',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
        'conditions' => [[
            'field' => 'days',
            'operator' => '>',
            'value' => '2',
            'extra_approver_type' => 'specific_user',
            'extra_approver_ref' => $this->approver->id,
        ]],
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    // 3-day leave (> 2) → the extra approver step is appended.
    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();
    expect((int) $leave->current_approver_id)->toBe($this->manager->id);

    // Manager approves → advances to the condition-added approver, still pending.
    actingAs($this->admin)->post(route('avana.cuti.approve', $leave));
    $leave->refresh();
    expect($leave->status)->toBe('pending');
    expect((int) $leave->current_approver_id)->toBe($this->approver->id);

    // Extra approver approves → finalized.
    actingAs($this->admin)->post(route('avana.cuti.approve', $leave));
    expect($leave->fresh()->status)->toBe('approved');
});

it('does not append the extra approver when the condition fails', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti Bersyarat',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
        'conditions' => [[
            'field' => 'days',
            'operator' => '>',
            'value' => '2',
            'extra_approver_type' => 'specific_user',
            'extra_approver_ref' => $this->approver->id,
        ]],
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    // 1-day leave (not > 2) → single-step workflow, one approval finalizes.
    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-10',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    actingAs($this->admin)->post(route('avana.cuti.approve', $leave));
    expect($leave->fresh()->status)->toBe('approved');
});

it('requires every step to approve a parallel workflow', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti Paralel',
        'request_type' => 'leave',
        'approval_mode' => 'parallel',
        'is_active' => true,
    ]);
    foreach ([$this->manager->id, $this->approver->id] as $order => $employeeId) {
        ApprovalStep::create([
            'tenant_id' => $this->tenant->id,
            'approval_workflow_id' => $workflow->id,
            'step_order' => $order + 1,
            'approver_type' => 'specific_user',
            'approver_user_id' => $employeeId,
        ]);
    }

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

    // Parallel: no single current approver.
    expect($leave->current_approver_id)->toBeNull();

    // Logins for the two employees the steps name as approvers — anyone else is
    // refused by the engine.
    $approverOne = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $approverTwo = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->manager->update(['user_id' => $approverOne->id]);
    $this->approver->update(['user_id' => $approverTwo->id]);

    // One approval (repeated by the same user) is not enough.
    ApprovalEngine::decide($leave->fresh(), $approverOne->id, 'approve');
    ApprovalEngine::decide($leave->fresh(), $approverOne->id, 'approve');
    expect($leave->fresh()->status)->toBe('pending');

    // A second distinct approver completes it and finalizes.
    ApprovalEngine::decide($leave->fresh(), $approverTwo->id, 'approve');
    expect($leave->fresh()->status)->toBe('approved');

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore + 3);
});

it('routes an izin (permission) request through its workflow', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Izin 2 Level',
        'request_type' => 'permission',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 2,
        'approver_type' => 'specific_user',
        'approver_user_id' => $this->approver->id,
    ]);

    actingAs($this->admin)->post(route('avana.cuti.izin.store'), [
        'employee_id' => $this->subject->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-10',
        'type' => 'izin_jam',
        'reason' => 'Keperluan',
    ]);

    $izin = PermissionRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();
    expect((int) $izin->current_approver_id)->toBe($this->manager->id);

    actingAs($this->admin)->post(route('avana.cuti.izin.approve', $izin));
    $izin->refresh();
    expect($izin->status)->toBe('pending');
    expect((int) $izin->current_approver_id)->toBe($this->approver->id);

    actingAs($this->admin)->post(route('avana.cuti.izin.approve', $izin));
    expect($izin->fresh()->status)->toBe('approved');
});

it('routes an attendance correction through its workflow and applies it on finalize', function (): void {
    $workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Koreksi 1 Level',
        'request_type' => 'attendance_correction',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);
    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $workflow->id,
        'step_order' => 1,
        'approver_type' => 'specific_user',
        'approver_user_id' => $this->approver->id,
    ]);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->subject->id,
        'date' => '2026-09-10',
        'correction_type' => 'manual',
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa absen',
        'current_approver_id' => $this->subject->manager_id,
        'status' => 'pending',
    ]);

    expect(ApprovalEngine::start($correction, $this->subject))->toBeTrue();
    expect((int) $correction->fresh()->current_approver_id)->toBe($this->approver->id);

    // Single step → the first approval finalizes and writes the attendance row.
    ApprovalEngine::decide($correction->fresh(), $this->admin->id, 'approve');

    expect($correction->fresh()->status)->toBe('approved');
    expect(
        Attendance::where('employee_id', $this->subject->id)
            ->where('status', 'present')
            ->exists(),
    )->toBeTrue();
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

it('refuses a decision from someone the current step is not routed to', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    // A colleague with a login but no stake in this workflow: step 1 is routed
    // to the subject's direct manager, not to them.
    $outsiderUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->approver->update(['user_id' => $outsiderUser->id]);

    expect(fn (): bool => ApprovalEngine::decide($leave->fresh(), $outsiderUser->id, 'approve'))
        ->toThrow(HttpException::class);

    expect($leave->fresh()->status)->toBe('pending');
    expect(ApprovalLog::where('approver_id', $outsiderUser->id)->count())->toBe(0);
});

it('lets the approver the step is routed to decide it', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    $managerUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->manager->update(['user_id' => $managerUser->id]);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    expect(ApprovalEngine::decide($leave->fresh(), $managerUser->id, 'approve'))->toBeTrue();

    $leave->refresh();
    expect($leave->status)->toBe('pending');
    expect((int) $leave->current_approver_id)->toBe($this->approver->id);
});

it('marks an HR admin decision on someone else’s step as an override', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    // Rina is admin_tenant_hr: allowed to step in, but the log says so.
    actingAs($this->admin)->post(route('avana.cuti.approve', $leave))->assertSessionHas('success');

    $instance = ApprovalRequest::where('approvable_id', $leave->id)
        ->where('approvable_type', LeaveRequest::class)->firstOrFail();

    expect(ApprovalLog::where('approval_request_id', $instance->id)
        ->where('approver_id', $this->admin->id)
        ->value('note'))->toContain('[override admin]');
});

it('tells the approver the request only advanced when more steps remain', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    actingAs($this->admin)->post(route('avana.cuti.approve', $leave))
        ->assertSessionHas('success', 'Persetujuan tercatat, menunggu tahap berikutnya');

    actingAs($this->admin)->post(route('avana.cuti.approve', $leave))
        ->assertSessionHas('success', 'Cuti disetujui');
});

it('advances the workflow when approving from the approval center', function (): void {
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

    // The unified inbox used to write `approved` straight onto the model, which
    // skipped step 2 entirely and left the instance stranded on step 1.
    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success', 'Persetujuan tercatat, menunggu tahap berikutnya');

    $leave->refresh();
    expect($leave->status)->toBe('pending');
    expect((int) $leave->current_approver_id)->toBe($this->approver->id);

    $instance = ApprovalRequest::where('approvable_id', $leave->id)
        ->where('approvable_type', LeaveRequest::class)->firstOrFail();
    expect((int) $instance->current_step)->toBe(2);
    expect(ApprovalLog::where('approval_request_id', $instance->id)->count())->toBe(1);

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore);

    // Second approval closes the workflow and draws the balance down once.
    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success', 'Cuti disetujui');

    $leave->refresh();
    $instance->refresh();
    $balance->refresh();

    expect($leave->status)->toBe('approved');
    expect($instance->status)->toBe('approved');
    expect((float) $balance->used)->toBe($usedBefore + 3);
});

it('closes the workflow when rejecting from the approval center', function (): void {
    twoStepLeaveWorkflow($this->tenant->id, $this->approver->id);

    actingAs($this->admin)->post(route('avana.cuti.store'), [
        'employee_id' => $this->subject->id,
        'leave_type_id' => $this->leaveType->id,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-12',
    ]);

    $leave = LeaveRequest::where('employee_id', $this->subject->id)->latest('id')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.approval.reject', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success');

    $leave->refresh();
    $instance = ApprovalRequest::where('approvable_id', $leave->id)
        ->where('approvable_type', LeaveRequest::class)->firstOrFail();

    expect($leave->status)->toBe('rejected');
    expect($instance->status)->toBe('rejected');
    expect(ApprovalLog::where('approval_request_id', $instance->id)
        ->where('action', 'reject')->count())->toBe(1);
});

it('still finalizes directly from the approval center without a workflow', function (): void {
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

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success', 'Cuti disetujui');

    $leave->refresh();
    $balance->refresh();

    expect($leave->status)->toBe('approved');
    expect((float) $balance->used)->toBe($usedBefore + 3);
    // whereDate, not whereBetween: `date` is a datetime column, so the string
    // upper bound would cut off the last day's 00:00:00 row.
    expect(Attendance::where('employee_id', $this->subject->id)
        ->whereDate('date', '>=', '2026-09-10')
        ->whereDate('date', '<=', '2026-09-12')
        ->where('status', 'leave')->count())->toBe(3);
});
