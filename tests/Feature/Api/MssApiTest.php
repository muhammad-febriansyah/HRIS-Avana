<?php

use App\Models\Claim;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
use App\Models\User;
use App\Models\WfhRequest;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    // Log in as an employee and treat them as the approving manager.
    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->manager = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->manager->tenant_id;

    // A direct report routed to this manager.
    $this->sub = Employee::forTenant($tenantId)
        ->where('id', '!=', $this->manager->id)
        ->where('status', 'active')
        ->firstOrFail();
    $this->sub->update(['manager_id' => $this->manager->id]);

    $leaveType = LeaveType::forTenant($tenantId)->firstOrFail();

    $this->leave = LeaveRequest::create([
        'tenant_id' => $tenantId, 'employee_id' => $this->sub->id, 'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'total_days' => 2,
        'reason' => 'Acara keluarga', 'current_approver_id' => $this->manager->id, 'status' => 'pending',
    ]);
    $this->overtime = OvertimeRequest::create([
        'tenant_id' => $tenantId, 'employee_id' => $this->sub->id, 'date' => '2026-07-20',
        'hours' => 3, 'reason' => 'Rilis', 'current_approver_id' => $this->manager->id, 'status' => 'pending',
    ]);
    $this->izin = PermissionRequest::create([
        'tenant_id' => $tenantId, 'employee_id' => $this->sub->id, 'date' => '2026-07-21',
        'type' => 'izin_jam', 'start_time' => '13:00:00', 'end_time' => '15:00:00',
        'reason' => 'Kontrol', 'current_approver_id' => $this->manager->id, 'status' => 'pending',
    ]);
    $this->wfh = WfhRequest::create([
        'tenant_id' => $tenantId, 'employee_id' => $this->sub->id, 'start_date' => '2026-07-22',
        'end_date' => '2026-07-22', 'reason' => 'Fokus', 'current_approver_id' => $this->manager->id, 'status' => 'pending',
    ]);
});

it('lists pending requests routed to the manager', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/mss/approvals')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'type', 'type_label', 'employee' => ['name', 'initials', 'avatar_color'], 'title', 'detail']],
        ])
        ->assertJsonFragment(['id' => 'leave-'.$this->leave->id]);
});

it('does not show requests routed to another approver', function (): void {
    $otherApprover = Employee::forTenant($this->manager->tenant_id)
        ->whereNotIn('id', [$this->manager->id, $this->sub->id])
        ->firstOrFail();

    LeaveRequest::create([
        'tenant_id' => $this->manager->tenant_id, 'employee_id' => $this->sub->id,
        'leave_type_id' => LeaveType::forTenant($this->manager->tenant_id)->firstOrFail()->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'total_days' => 1,
        'reason' => 'Lain', 'current_approver_id' => $otherApprover->id, 'status' => 'pending',
    ]);

    ($this->auth)()->getJson('/api/v1/mss/approvals')->assertOk()->assertJsonCount(4, 'data');
});

it('approves one request by its composite key', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/lembur-'.$this->overtime->id.'/act', ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('message', 'Permintaan disetujui.');

    expect($this->overtime->fresh()->status)->toBe('approved');
});

it('rejects one request with a reason', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/izin-'.$this->izin->id.'/act', ['action' => 'reject', 'reason' => 'Tidak sesuai'])
        ->assertOk();

    expect($this->izin->fresh()->status)->toBe('rejected');
});

it('rejects an unknown or foreign key with 404', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/leave-999999/act', ['action' => 'approve'])
        ->assertNotFound();
});

it('bulk-approves several requests', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/bulk', [
            'ids' => ['leave-'.$this->leave->id, 'wfh-'.$this->wfh->id],
            'action' => 'approve',
        ])
        ->assertOk()
        ->assertJsonPath('processed', 2);

    expect($this->leave->fresh()->status)->toBe('approved');
    expect($this->wfh->fresh()->status)->toBe('approved');
});

it('lists the manager team', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/mss/team')
        ->assertOk()
        ->assertJsonFragment(['id' => $this->sub->id, 'name' => $this->sub->full_name]);
});

it('reports is_manager on the profile', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/me/profile')
        ->assertOk()
        ->assertJsonPath('data.is_manager', true);
});

function mssPendingClaim(object $test): Claim
{
    return Claim::create([
        'tenant_id' => $test->manager->tenant_id,
        'employee_id' => $test->sub->id,
        'claim_type' => 'transport',
        'title' => 'Transport klien',
        'amount' => 250000,
        'claim_date' => '2026-07-08',
        'description' => 'Taksi ke kantor klien',
        'current_approver_id' => $test->manager->id,
        'status' => 'pending',
    ]);
}

it('lists a reimbursement in the manager approvals as a reimburse item', function (): void {
    $claim = mssPendingClaim($this);

    ($this->auth)()
        ->getJson('/api/v1/mss/approvals')
        ->assertOk()
        ->assertJsonFragment(['id' => 'reimburse-'.$claim->id])
        ->assertJsonFragment(['type_label' => 'Reimbursement'])
        ->assertJsonFragment(['detail' => 'Rp 250.000']);
});

it('approves a reimbursement', function (): void {
    $claim = mssPendingClaim($this);

    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/reimburse-'.$claim->id.'/act', ['action' => 'approve'])
        ->assertOk();

    $fresh = $claim->fresh();
    expect($fresh->status)->toBe('approved');
    expect($fresh->approver_id)->toBe($this->manager->user_id);
    expect($fresh->approved_at)->not->toBeNull();
});

it('rejects a reimbursement', function (): void {
    $claim = mssPendingClaim($this);

    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/reimburse-'.$claim->id.'/act', ['action' => 'reject'])
        ->assertOk();

    expect($claim->fresh()->status)->toBe('rejected');
});
