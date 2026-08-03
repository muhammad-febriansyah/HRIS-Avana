<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeaveApproval;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * A pending leave request for the seeded tenant.
 */
function makeApproverLeave(int $tenantId): LeaveRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $leaveType = LeaveType::forTenant($tenantId)->firstOrFail();

    return LeaveRequest::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'total_days' => 2,
        'reason' => 'Keperluan keluarga',
        'status' => 'pending',
        'current_approver_id' => $employee->id,
    ]);
}

it('records the approver as an employee id, not a user id', function (): void {
    $leave = makeApproverLeave($this->tenant->id);

    // An approver whose user id and employee id deliberately differ, so writing
    // the wrong one cannot pass by coincidence.
    $approver = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $approver->id,
        'employee_number' => 'APR-0001',
        'full_name' => 'Atasan Uji',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    expect($employee->id)->not->toBe($approver->id);

    LeaveApproval::finalize($leave, $approver->id);

    expect($leave->fresh()->current_approver_id)->toBe($employee->id);
});

it('leaves the assigned approver alone when the actor has no employee record', function (): void {
    $leave = makeApproverLeave($this->tenant->id);
    $original = $leave->current_approver_id;

    // Rina is an HR login with no employee row of her own.
    LeaveApproval::finalize($leave, $this->admin->id);

    $fresh = $leave->fresh();

    expect($fresh->status)->toBe('approved')
        ->and($fresh->current_approver_id)->toBe($original);
});

it('still approves when no approver is named at all', function (): void {
    $leave = makeApproverLeave($this->tenant->id);

    LeaveApproval::finalize($leave);

    expect($leave->fresh()->status)->toBe('approved');
});
