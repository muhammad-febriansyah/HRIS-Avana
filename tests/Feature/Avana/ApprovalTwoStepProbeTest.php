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

    // Step 1 the direct manager, step 2 HC — exactly the shape the client set up.
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
});

it('probe: a leave goes manager then HC, not straight to approved', function (): void {
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

    $leave = LeaveRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();
    $instance = ApprovalRequest::where('approvable_id', $leave->id)->first();

    dump('SETELAH AJUKAN  status='.$leave->status
        .' current_approver='.var_export($leave->current_approver_id, true)
        .' instance='.($instance ? 'step '.$instance->current_step : 'TIDAK ADA'));

    expect($instance)->not->toBeNull();
    expect((int) $leave->current_approver_id)->toBe((int) $this->manager->id);

    // The manager approves from the web approval centre.
    $webResponse = actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]));

    dump('ATASAN LEWAT WEB -> HTTP '.$webResponse->status());

    // The manager's real path is the phone.
    $this->app['auth']->forgetGuards();
    $managerToken = $this->postJson('/api/v1/auth/login', [
        'email' => $this->manager->user->email,
        'password' => 'password',
    ])->json('access_token');
    $this->app['auth']->forgetGuards();

    $mss = $this->withHeader('Authorization', 'Bearer '.$managerToken)
        ->postJson('/api/v1/mss/approvals/leave-'.$leave->id.'/act', ['action' => 'approve']);

    dump('ATASAN LEWAT HP -> HTTP '.$mss->status().' '.json_encode($mss->json('message')));

    $leave->refresh();
    $instance = ApprovalRequest::where('approvable_id', $leave->id)->first();

    dump('SETELAH ATASAN  status='.$leave->status
        .' current_approver='.var_export($leave->current_approver_id, true)
        .' instance='.($instance ? $instance->status.' step '.$instance->current_step : 'TIDAK ADA'));

    // Must still be pending, now waiting on HC — not finalized by the manager.
    expect($leave->status)->toBe('pending');
    expect($instance->current_step)->toBe(2);

    // And HC must be able to see it in their own queue.
    $hrEmployee = Employee::forTenant($this->tenant->id)->where('user_id', $this->hr->id)->first();

    if ($hrEmployee !== null) {
        $visible = ApprovalEngine::pendingApprovableIdsFor(LeaveRequest::class, $hrEmployee);
        dump('ANTREAN HC (mobile) = '.json_encode($visible));
    }

    $page = actingAs($this->hr)->get(route('avana.approval'))->assertOk();
    $props = $page->viewData('page')['props'];
    $ids = collect($props['pending'])->where('type', 'leave')->pluck('id');
    dump('ANTREAN HC (web) memuat cuti ini = '.($ids->contains($leave->id) ? 'YA' : 'TIDAK'));

    expect($ids)->toContain($leave->id);
});
