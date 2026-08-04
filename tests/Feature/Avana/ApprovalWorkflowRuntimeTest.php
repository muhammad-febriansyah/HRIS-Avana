<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\RoleMenuVisibility;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Support\AvanaNav;
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

/**
 * The client's own shape: step 2 names one employee outright — someone whose
 * role carries no approval module and whose sidebar the tenant hid the approval
 * centre from. Everything below covers the request reaching that person.
 */
function nameSecondStepApprover(mixed $test): Employee
{
    $approver = Employee::forTenant($test->tenant->id)
        ->whereNotNull('user_id')
        ->where('id', '!=', $test->manager->id)
        ->where('id', '!=', $test->staff->id)
        ->where('is_top_approver', false)
        // Nobody reports to them, so nothing but the workflow could put a
        // request on their desk.
        ->whereDoesntHave('subordinates')
        ->whereDoesntHave('user.roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin_tenant_hr', 'manager']))
        ->firstOrFail();

    ApprovalStep::where('approval_workflow_id', $test->workflow->id)
        ->where('step_order', 2)
        ->update([
            'approver_type' => 'specific_user',
            'approver_role_id' => null,
            'approver_user_id' => $approver->id,
        ]);

    // …and the tenant hid the approval centre from that role (Hak Akses →
    // kolom Tampil), which used to close the screen before anything else ran.
    foreach ($approver->user->roles as $role) {
        RoleMenuVisibility::updateOrCreate(
            ['role_id' => $role->id, 'menu_key' => AvanaNav::APPROVAL_CENTRE_KEY],
            ['tenant_id' => $test->tenant->id, 'is_visible' => false],
        );
    }

    return $approver->refresh();
}

it('routes the second step to the employee it names', function (): void {
    $approver = nameSecondStepApprover($this);
    $leave = ($this->submitLeave)();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    expect((int) $leave->fresh()->current_approver_id)->toBe((int) $approver->id);
});

it('shows the named approver the approval centre their role hides', function (): void {
    $approver = nameSecondStepApprover($this);
    $leave = ($this->submitLeave)();

    // Nothing is waiting on them yet, so the menu stays hidden.
    $before = collect(actingAs($approver->user)->get(route('dashboard'))->viewData('page')['props']['nav'])
        ->flatMap(fn (array $group): array => $group['items'])
        ->flatMap(fn (array $item): array => $item['children'] ?? [$item])
        ->pluck('id');

    expect($before)->not->toContain(AvanaNav::APPROVAL_CENTRE_KEY);

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    $after = collect(actingAs($approver->user)->get(route('dashboard'))->viewData('page')['props']['nav'])
        ->flatMap(fn (array $group): array => $group['items'])
        ->flatMap(fn (array $item): array => $item['children'] ?? [$item])
        ->pluck('id');

    expect($after)->toContain(AvanaNav::APPROVAL_CENTRE_KEY);
});

it('lets the named approver open and finish the request', function (): void {
    $approver = nameSecondStepApprover($this);
    $leave = ($this->submitLeave)();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    $page = actingAs($approver->user)->get(route('avana.approval'))->assertOk();

    expect(collect($page->viewData('page')['props']['pending'])->where('type', 'leave')->pluck('id'))
        ->toContain($leave->id);

    actingAs($approver->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    expect($leave->fresh()->status)->toBe('approved');
});

it('tells each approver when the request lands on their step', function (): void {
    $approver = nameSecondStepApprover($this);
    $leave = ($this->submitLeave)();

    expect(Notification::where('user_id', $this->manager->user_id)->where('type', 'approval')->count())->toBe(1);
    expect(Notification::where('user_id', $approver->user_id)->where('type', 'approval')->count())->toBe(0);

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    $notification = Notification::where('user_id', $approver->user_id)
        ->where('type', 'approval')
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull();
    expect($notification->data['link'])->toBe(['type' => 'leave', 'id' => $leave->id]);
});

it('opens manager self-service on mobile to the named approver', function (): void {
    $approver = nameSecondStepApprover($this);
    $leave = ($this->submitLeave)();

    $login = fn (): string => $this->postJson('/api/v1/auth/login', [
        'email' => $approver->user->email,
        'password' => 'password',
    ])->json('access_token');

    $token = $login();
    $this->app['auth']->forgetGuards();

    expect($this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/profile')->json('data.is_manager'))
        ->toBeFalse();

    $this->app['auth']->forgetGuards();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    $this->app['auth']->forgetGuards();
    $token = $login();
    $this->app['auth']->forgetGuards();

    expect($this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/me/profile')->json('data.is_manager'))
        ->toBeTrue();
});

it('shows a group step on web to a role holder who is not HR', function (): void {
    $reviewerRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Reviewer Cuti',
        'code' => 'reviewer_cuti',
    ]);

    $reviewer = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('id', '!=', $this->manager->id)
        ->where('id', '!=', $this->staff->id)
        ->where('is_top_approver', false)
        ->whereDoesntHave('subordinates')
        ->firstOrFail();

    $reviewer->user->roles()->sync([$reviewerRole->id]);

    ApprovalStep::where('approval_workflow_id', $this->workflow->id)
        ->where('step_order', 2)
        ->update(['approver_type' => 'role', 'approver_role_id' => $reviewerRole->id]);

    $leave = ($this->submitLeave)();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    // A group step carries no `current_approver_id`, so the list used to show
    // this to nobody but a company-wide role.
    $props = actingAs($reviewer->user->fresh())->get(route('avana.approval'))->assertOk()->viewData('page')['props'];

    expect(collect($props['pending'])->contains(
        fn (array $row): bool => $row['type'] === 'leave' && $row['id'] === $leave->id,
    ))->toBeTrue();

    actingAs($reviewer->user->fresh())
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    expect($leave->fresh()->status)->toBe('approved');
});

it('carries a claim workflow all the way through the approval centre', function (): void {
    $approver = nameSecondStepApprover($this);

    $claimWorkflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Klaim: Atasan lalu Finance',
        'request_type' => 'reimbursement',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $claimWorkflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $claimWorkflow->id,
        'step_order' => 2,
        'approver_type' => 'specific_user',
        'approver_user_id' => $approver->id,
    ]);

    $claim = Reimbursement::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->staff->id,
        'category' => 'operasional',
        'title' => 'Bensin kunjungan',
        'amount' => 180000,
        'expense_date' => now()->subDay()->toDateString(),
        'description' => 'Kunjungan ke gudang',
        'status' => 'pending',
    ]);

    expect(ApprovalEngine::start($claim, $this->staff))->toBeTrue();
    expect((int) $claim->fresh()->current_approver_id)->toBe((int) $this->manager->id);

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'klaim', 'id' => $claim->id]))
        ->assertRedirect();

    expect($claim->fresh()->status)->toBe('pending');
    expect((int) $claim->fresh()->current_approver_id)->toBe((int) $approver->id);

    // The named approver holds no `claim` module and their role hides the menu —
    // the claim used to be unreachable for them on web entirely.
    $props = actingAs($approver->user)->get(route('avana.approval'))->assertOk()->viewData('page')['props'];

    expect(collect($props['pending'])->contains(
        fn (array $row): bool => $row['type'] === 'klaim' && $row['id'] === $claim->id,
    ))->toBeTrue();

    actingAs($approver->user)
        ->post(route('avana.approval.approve', ['type' => 'klaim', 'id' => $claim->id]))
        ->assertRedirect();

    $claim->refresh();

    expect($claim->status)->toBe('approved');
    expect((int) $claim->approver_id)->toBe((int) $approver->user_id);
});

it('runs the duty travel workflow the wizard offers', function (): void {
    $approver = nameSecondStepApprover($this);

    $travelWorkflow = ApprovalWorkflow::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Dinas: Atasan lalu GA',
        'request_type' => 'duty_travel',
        'approval_mode' => 'sequential',
        'is_active' => true,
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $travelWorkflow->id,
        'step_order' => 1,
        'approver_type' => 'direct_manager',
    ]);

    ApprovalStep::create([
        'tenant_id' => $this->tenant->id,
        'approval_workflow_id' => $travelWorkflow->id,
        'step_order' => 2,
        'approver_type' => 'specific_user',
        'approver_user_id' => $approver->id,
    ]);

    // The employee files their own trip from ESS.
    actingAs($this->staff->user)
        ->post(route('avana.saya.perjalanan-dinas.store'), [
            'destination' => 'Surabaya',
            'purpose' => 'Audit cabang',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'transport' => 'Pesawat',
            'estimated_cost' => 2500000,
        ])
        ->assertRedirect();

    $travel = DutyTravel::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect(ApprovalRequest::where('approvable_type', DutyTravel::class)->where('approvable_id', $travel->id)->exists())
        ->toBeTrue();
    expect((int) $travel->current_approver_id)->toBe((int) $this->manager->id);

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'dinas', 'id' => $travel->id]))
        ->assertRedirect();

    // Step 1 only advances the trip; it is not approved yet.
    $travel->refresh();
    expect($travel->status)->toBe('pending');
    expect((int) $travel->current_approver_id)->toBe((int) $approver->id);

    $props = actingAs($approver->user)->get(route('avana.approval'))->assertOk()->viewData('page')['props'];

    expect(collect($props['pending'])->contains(
        fn (array $row): bool => $row['type'] === 'dinas' && $row['id'] === $travel->id,
    ))->toBeTrue();

    actingAs($approver->user)
        ->post(route('avana.approval.approve', ['type' => 'dinas', 'id' => $travel->id]))
        ->assertRedirect();

    $travel->refresh();

    expect($travel->status)->toBe('approved');
    expect((int) $travel->approved_by)->toBe((int) $approver->user_id);
});

it('leaves duty travel with the manager when no workflow is configured', function (): void {
    actingAs($this->staff->user)
        ->post(route('avana.saya.perjalanan-dinas.store'), [
            'destination' => 'Bandung',
            'purpose' => 'Pelatihan',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect();

    $travel = DutyTravel::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect(ApprovalRequest::where('approvable_type', DutyTravel::class)->where('approvable_id', $travel->id)->exists())
        ->toBeFalse();
    expect((int) $travel->current_approver_id)->toBe((int) $this->manager->id);

    actingAs($this->hr)
        ->post(route('avana.dinas.approve', ['dutyTravel' => $travel->id]))
        ->assertRedirect();

    expect($travel->fresh()->status)->toBe('approved');
});

it('never lets a step land on the person who filed the request', function (): void {
    // The step names one employee for the whole module — including their own
    // requests, which would make them their own approver.
    ApprovalStep::where('approval_workflow_id', $this->workflow->id)
        ->where('step_order', 2)
        ->delete();

    ApprovalStep::where('approval_workflow_id', $this->workflow->id)
        ->where('step_order', 1)
        ->update([
            'approver_type' => 'specific_user',
            'approver_user_id' => $this->staff->id,
        ]);

    $leave = ($this->submitLeave)();

    // Routed to the requester's manager instead of back to themselves.
    expect((int) $leave->fresh()->current_approver_id)->toBe((int) $this->manager->id);

    actingAs($this->staff->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertForbidden();

    expect($leave->fresh()->status)->toBe('pending');

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertRedirect();

    expect($leave->fresh()->status)->toBe('approved');
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
