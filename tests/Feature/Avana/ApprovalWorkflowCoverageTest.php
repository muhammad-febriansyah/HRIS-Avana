<?php

use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceCorrection;
use App\Models\DataChangeRequest;
use App\Models\DutyTravel;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WfhRequest;
use App\Services\ApprovalEngine;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

/**
 * Covers the request types the workflow suite did not reach: overtime, WFH and
 * personal-data changes end to end, plus the "approver puncak" shortcut on the
 * types that used to leave a director's own request waiting on nobody.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->hr = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->hr->tenant_id);

    $this->staff = Employee::forTenant($this->tenant->id)
        ->where('is_top_approver', false)
        ->whereNotNull('user_id')
        ->whereNotNull('manager_id')
        ->whereHas('manager', fn ($query) => $query->whereNotNull('user_id'))
        ->firstOrFail();

    $this->manager = Employee::forTenant($this->tenant->id)->findOrFail($this->staff->manager_id);

    $this->director = Employee::forTenant($this->tenant->id)
        ->where('is_top_approver', true)
        ->whereNotNull('user_id')
        ->firstOrFail();

    $this->hrRole = Role::where('code', 'admin_tenant_hr')->firstOrFail();

    /**
     * Build an active workflow of the given type from a list of step
     * definitions, e.g. [['approver_type' => 'direct_manager']].
     *
     * @var Closure(string, array<int, array<string, mixed>>): ApprovalWorkflow
     */
    $this->makeWorkflow = function (string $type, array $steps): ApprovalWorkflow {
        $workflow = ApprovalWorkflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Uji '.$type,
            'request_type' => $type,
            'approval_mode' => 'sequential',
            'is_active' => true,
        ]);

        foreach ($steps as $index => $step) {
            ApprovalStep::create([
                'tenant_id' => $this->tenant->id,
                'approval_workflow_id' => $workflow->id,
                'step_order' => $index + 1,
            ] + $step);
        }

        return $workflow;
    };
});

it('runs an overtime request through both steps of its workflow', function (): void {
    ($this->makeWorkflow)('overtime', [
        ['approver_type' => 'direct_manager'],
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);

    actingAs($this->staff->user)
        ->post(route('avana.saya.lembur.store'), [
            'date' => now()->subDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Tutup buku',
        ])
        ->assertRedirect();

    $overtime = OvertimeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect((int) $overtime->current_approver_id)->toBe((int) $this->manager->id);

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'lembur', 'id' => $overtime->id]))
        ->assertRedirect();

    // Step 1 only advances: HC still has to see it.
    expect($overtime->fresh()->status)->toBe('pending');
    expect(ApprovalRequest::where('approvable_id', $overtime->id)->firstOrFail()->current_step)->toBe(2);

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'lembur', 'id' => $overtime->id]))
        ->assertRedirect();

    expect($overtime->fresh()->status)->toBe('approved');
});

it('runs a personal data change through its workflow and applies it on the last step', function (): void {
    ($this->makeWorkflow)('data_change', [
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);

    actingAs($this->staff->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'phone', 'value' => '081200001111']],
            'reason' => 'Ganti nomor',
        ])
        ->assertRedirect();

    $change = DataChangeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    // A role step names no single person, so the request carries no approver
    // and is surfaced to every holder of the role instead.
    expect($change->current_approver_id)->toBeNull();
    expect(ApprovalRequest::where('approvable_id', $change->id)->where('approvable_type', DataChangeRequest::class)->exists())->toBeTrue();

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'data', 'id' => $change->id]))
        ->assertRedirect();

    expect($change->fresh()->status)->toBe('approved');
    expect($this->staff->fresh()->phone)->toBe('081200001111');
});

it('runs a WFH request through the workflow the wizard now offers', function (): void {
    ($this->makeWorkflow)('wfh', [
        ['approver_type' => 'direct_manager'],
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);

    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect((int) $wfh->current_approver_id)->toBe((int) $this->manager->id);

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'wfh', 'id' => $wfh->id]))
        ->assertRedirect();

    expect($wfh->fresh()->status)->toBe('pending');
    expect(ApprovalRequest::where('approvable_id', $wfh->id)->where('approvable_type', WfhRequest::class)->firstOrFail()->current_step)->toBe(2);

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'wfh', 'id' => $wfh->id]))
        ->assertRedirect();

    expect($wfh->fresh()->status)->toBe('approved');
});

it('only advances a WFH workflow when approved from the module screen', function (): void {
    ($this->makeWorkflow)('wfh', [
        ['approver_type' => 'direct_manager'],
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);

    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    // Approving the first step from the WFH screen itself used to mark the
    // request approved outright, skipping every remaining step.
    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.approve', ['wfh' => $wfh->id]))
        ->assertSessionHas('success', 'Persetujuan tercatat, menunggu tahap berikutnya');

    expect($wfh->fresh()->status)->toBe('pending');
});

it('sends an approver somewhere they may go once their step is done', function (): void {
    ($this->makeWorkflow)('wfh', [
        ['approver_type' => 'direct_manager'],
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);

    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    // The manager's licence for the approval centre was the request itself, so
    // approving it took the screen away: redirecting "back" answered 403 to
    // the click that had just worked.
    actingAs($this->manager->user)
        ->from(route('avana.approval'))
        ->post(route('avana.approval.approve', ['type' => 'wfh', 'id' => $wfh->id]))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success');
});

it('makes a parallel flow wait for every step, not for a headcount', function (): void {
    // Manager + HR, both opened at once. Counting distinct approvers instead of
    // steps let two holders of the HR role finish a flow the manager whose step
    // it also names had never seen.
    $workflow = ($this->makeWorkflow)('wfh', [
        ['approver_type' => 'direct_manager'],
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);
    $workflow->update(['approval_mode' => 'parallel']);

    // Two holders of the role step 2 names, each with an employee record of
    // their own so the engine sees them as that step's approvers rather than
    // as an admin stepping in.
    $roleHolders = collect(['EMP-9998', 'EMP-9999'])->map(function (string $number): User {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user->roles()->sync([$this->hrRole->id]);

        Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'employee_number' => $number,
            'full_name' => 'HC '.$number,
            'join_date' => now()->subYear()->toDateString(),
            'status' => 'active',
        ]);

        return $user;
    });

    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    ApprovalEngine::decide($wfh->fresh(), $roleHolders[0]->id, 'approve');
    ApprovalEngine::decide($wfh->fresh(), $roleHolders[1]->id, 'approve');

    // Two approvals from the same role only answer the one step that role is
    // named on; the manager's step is still open.
    expect($wfh->fresh()->status)->toBe('pending');

    ApprovalEngine::decide($wfh->fresh(), $this->manager->user_id, 'approve');

    expect($wfh->fresh()->status)->toBe('approved');
});

it('counts an HR approval against the role step HR actually holds', function (): void {
    // Step 2 names the HR role, which this admin carries — but they have no
    // employee record, so the engine used to see them as holding no step and
    // credited their approval to step 1, the manager's.
    $workflow = ($this->makeWorkflow)('wfh', [
        ['approver_type' => 'direct_manager'],
        ['approver_type' => 'role', 'approver_role_id' => $this->hrRole->id],
    ]);
    $workflow->update(['approval_mode' => 'parallel']);

    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    ApprovalEngine::decide($wfh->fresh(), $this->hr->id, 'approve');

    $instance = ApprovalRequest::where('approvable_type', WfhRequest::class)
        ->where('approvable_id', $wfh->id)
        ->firstOrFail();

    expect(ApprovalLog::where('approval_request_id', $instance->id)->value('step_order'))->toBe(2);
    expect($wfh->fresh()->status)->toBe('pending');

    // The manager's own step still has to be answered by the manager.
    ApprovalEngine::decide($wfh->fresh(), $this->manager->user_id, 'approve');

    expect($wfh->fresh()->status)->toBe('approved');
});

it('lets an admin stepping into a parallel flow answer a step that is still open', function (): void {
    // HR holds no step here — and, in this tenant, no employee record either.
    // Their override used to be accepted and then recorded nowhere, so the
    // request never moved however many times they clicked.
    $workflow = ($this->makeWorkflow)('wfh', [
        ['approver_type' => 'direct_manager'],
    ]);
    $workflow->update(['approval_mode' => 'parallel']);

    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'wfh', 'id' => $wfh->id]))
        ->assertRedirect();

    expect($wfh->fresh()->status)->toBe('approved');
});

it('adds the conditional approver an overtime hours rule asks for', function (): void {
    $workflow = ($this->makeWorkflow)('overtime', [
        ['approver_type' => 'direct_manager'],
    ]);

    // "Lebih dari 2 jam → tambah HC." Conditions could only read total_days and
    // amount before, neither of which an overtime request carries.
    $workflow->update(['conditions' => [[
        'field' => 'hours',
        'operator' => '>',
        'value' => '2',
        'extra_approver_type' => 'role',
        'extra_approver_ref' => $this->hrRole->id,
    ]]]);

    actingAs($this->staff->user)
        ->post(route('avana.saya.lembur.store'), [
            'date' => now()->subDay()->toDateString(),
            'start_time' => '18:00',
            'end_time' => '21:00',
            'reason' => 'Rilis',
        ])
        ->assertRedirect();

    $overtime = OvertimeRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'lembur', 'id' => $overtime->id]))
        ->assertRedirect();

    // The extra step exists, so the manager's approval only advances.
    expect($overtime->fresh()->status)->toBe('pending');

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'lembur', 'id' => $overtime->id]))
        ->assertRedirect();

    expect($overtime->fresh()->status)->toBe('approved');
});

it('reads a duty travel budget as the nominal a condition compares', function (): void {
    $workflow = ($this->makeWorkflow)('duty_travel', [
        ['approver_type' => 'direct_manager'],
    ]);

    $workflow->update(['conditions' => [[
        'field' => 'amount',
        'operator' => '>',
        'value' => '5000000',
        'extra_approver_type' => 'role',
        'extra_approver_ref' => $this->hrRole->id,
    ]]]);

    actingAs($this->staff->user)
        ->post(route('avana.saya.perjalanan-dinas.store'), [
            'destination' => 'Surabaya',
            'purpose' => 'Kunjungan klien',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
            'estimated_cost' => 9000000,
        ])
        ->assertRedirect();

    $travel = DutyTravel::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    actingAs($this->manager->user)
        ->post(route('avana.approval.approve', ['type' => 'dinas', 'id' => $travel->id]))
        ->assertRedirect();

    expect($travel->fresh()->status)->toBe('pending');

    actingAs($this->hr)
        ->post(route('avana.approval.approve', ['type' => 'dinas', 'id' => $travel->id]))
        ->assertRedirect();

    expect($travel->fresh()->status)->toBe('approved');
});

it('refuses a condition on a value the chosen module does not carry', function (): void {
    actingAs($this->hr)
        ->post(route('avana.approval-workflow.store'), [
            'request_type' => 'attendance_correction',
            'approval_mode' => 'sequential',
            'is_active' => true,
            'steps' => [['approver_type' => 'direct_manager']],
            'conditions' => [[
                'field' => 'amount',
                'operator' => '>',
                'value' => '1000000',
                'extra_approver_type' => 'role',
                'extra_approver_ref' => $this->hrRole->id,
            ]],
        ])
        ->assertSessionHasErrors('conditions.0.field');
});

it('refuses a module the engine has no request to route', function (): void {
    // "Permintaan Dokumen" had no model behind it, so a flow built for it was
    // configuration that could never run.
    actingAs($this->hr)
        ->post(route('avana.approval-workflow.store'), [
            'request_type' => 'document_request',
            'approval_mode' => 'sequential',
            'is_active' => true,
            'steps' => [['approver_type' => 'direct_manager']],
        ])
        ->assertSessionHasErrors('request_type');

    actingAs($this->hr)
        ->get(route('avana.approval-workflow'))
        ->assertInertia(fn ($page) => $page->where(
            'modules',
            fn ($modules): bool => ! collect($modules)->contains('key', 'document_request'),
        ));
});

it('offers WFH as a module the approval wizard can configure', function (): void {
    actingAs($this->hr)
        ->get(route('avana.approval-workflow'))
        ->assertInertia(fn ($page) => $page->where(
            'modules',
            fn ($modules): bool => collect($modules)->contains('key', 'wfh'),
        ));
});

it('leaves a WFH request filed from the admin screen with the employee manager', function (): void {
    // No workflow: without an approver of its own the request used to land in
    // nobody's queue but HR's.
    actingAs($this->hr)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $this->staff->id,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'reason' => 'Kerja dari rumah',
        ])
        ->assertRedirect();

    $wfh = WfhRequest::where('employee_id', $this->staff->id)->latest('id')->firstOrFail();

    expect((int) $wfh->current_approver_id)->toBe((int) $this->manager->id);
});

it('settles a director own izin on the spot', function (): void {
    actingAs($this->director->user)
        ->post(route('avana.saya.izin.store'), [
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'type' => 'izin',
            'reason' => 'Urusan keluarga',
        ])
        ->assertRedirect();

    $permission = PermissionRequest::where('employee_id', $this->director->id)->latest('id')->firstOrFail();

    expect($permission->status)->toBe('approved');
});

it('settles a director own attendance correction on the spot', function (): void {
    actingAs($this->director->user)
        ->post(route('avana.saya.koreksi-absensi.store'), [
            'date' => now()->subDay()->toDateString(),
            'requested_clock_in' => '08:00',
            'reason' => 'Lupa absen',
        ])
        ->assertRedirect();

    $correction = AttendanceCorrection::where('employee_id', $this->director->id)->latest('id')->firstOrFail();

    expect($correction->status)->toBe('approved');
});

it('settles a director own duty travel on the spot', function (): void {
    actingAs($this->director->user)
        ->post(route('avana.saya.perjalanan-dinas.store'), [
            'destination' => 'Surabaya',
            'purpose' => 'Kunjungan klien',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->addDay()->toDateString(),
        ])
        ->assertRedirect();

    $travel = DutyTravel::where('employee_id', $this->director->id)->latest('id')->firstOrFail();

    expect($travel->status)->toBe('approved');
});

it('still makes somebody else review a director own data change', function (): void {
    // These fields include the bank account payroll pays into, so this one
    // request type deliberately has no self-approval shortcut.
    actingAs($this->director->user)
        ->post(route('avana.saya.perubahan-data.store'), [
            'changes' => [['field' => 'bank_account_number', 'value' => '9998887776']],
            'reason' => 'Pindah bank',
        ])
        ->assertRedirect();

    $change = DataChangeRequest::where('employee_id', $this->director->id)->latest('id')->firstOrFail();

    expect($change->status)->toBe('pending');
});
