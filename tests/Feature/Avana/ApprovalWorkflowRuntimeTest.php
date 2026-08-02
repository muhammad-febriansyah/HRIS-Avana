<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Employee;
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

    $this->hr = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->hr->tenant_id);

    // Someone who reports to a manager who can actually log in and approve.
    $this->staff = Employee::forTenant($this->tenant->id)
        ->where('is_top_approver', false)
        ->whereNotNull('user_id')
        ->whereNotNull('manager_id')
        ->whereHas('manager', fn ($query) => $query->whereNotNull('user_id'))
        ->firstOrFail();

    $this->manager = Employee::forTenant($this->tenant->id)->findOrFail($this->staff->manager_id);

    $hrRole = Role::where('code', 'admin_tenant_hr')->firstOrFail();

    // Step 1 the direct manager, step 2 HC — the shape the client configured.
    $this->workflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cuti: Atasan lalu HC',
        'request_type' => 'leave',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $this->workflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $this->workflow->id,
        'step_order' => 2,
        'approver_type' => 'role',
        'approver_role_id' => $hrRole->id,
    ]);

    $this->leaveType = LeaveType::forTenant($this->tenant->id)->firstOrFail();

    $this->submitLeave = function (): LeaveRequest {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $this->staff->user->email,
            'password' => 'password',
        ])->json('access_token');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/me/leave-requests', [
                'leave_type_id' => $this->leaveType->id,
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addWeek()->toDateString(),
                'reason' => 'Uji alur dua tahap',
            ])
            ->assertCreated();

        $this->app['auth']->forgetGuards();

        return LeaveRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();
    };
});

it('routes a new leave to the first step of its workflow', function (): void {
    $leave = ($this->submitLeave)();

    $instance = ApprovalRequest::where('approvable_id', $leave->id)->first();

    expect($instance)->not->toBeNull();
    expect($instance->current_step)->toBe(1);
    expect((int) $leave->current_approver_id)->toBe((int) $this->manager->id);
});

it('lets the manager the workflow named approve from the web', function (): void {
    $leave = ($this->submitLeave)();

    // This manager holds no approval module of their own — the workflow is
    // what routes the request to them, and the screen used to answer 403.
    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    $leave->refresh();
    $instance = ApprovalRequest::where('approvable_id', $leave->id)->firstOrFail();

    // Advanced, not finalized: HC has not seen it yet.
    expect($leave->status)->toBe('pending');
    expect($instance->current_step)->toBe(2);
    expect($instance->status)->toBe('pending');
});

it('hands the second step to HC and finishes there', function (): void {
    $leave = ($this->submitLeave)();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    $page = actingAs($this->hr)->get(route('avana.approval'))->assertOk();
    $ids = collect($page->viewData('page')['props']['pending'])
        ->where('type', 'leave')
        ->pluck('id');

    expect($ids)->toContain($leave->id);

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    expect($leave->fresh()->status)->toBe('approved');
    expect(ApprovalRequest::where('approvable_id', $leave->id)->first()->status)->toBe('approved');
});

it('surfaces a group step to every holder of the role', function (): void {
    $leave = ($this->submitLeave)();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]));

    // A group step owns no single approver, so the request carries none.
    expect($leave->fresh()->current_approver_id)->toBeNull();

    $hrEmployee = Employee::forTenant($this->tenant->id)->where('user_id', $this->hr->id)->first();

    if ($hrEmployee !== null) {
        expect(ApprovalEngine::pendingApprovableIdsFor(LeaveRequest::class, $hrEmployee))
            ->toContain($leave->id);
    }
});

it('closes the screen again once nothing is waiting on that approver', function (): void {
    $leave = ($this->submitLeave)();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    // The step has moved on, and the manager's licence went with it.
    actingAs($this->manager->user)
        ->get(route('avana.approval'))
        ->assertForbidden();
});

it('still refuses someone no request is waiting on', function (): void {
    $stranger = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('id', '!=', $this->manager->id)
        ->where('id', '!=', $this->staff->id)
        ->whereDoesntHave('user.roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin_tenant_hr', 'manager']))
        ->firstOrFail();

    actingAs($stranger->user)
        ->get(route('avana.approval'))
        ->assertForbidden();
});
