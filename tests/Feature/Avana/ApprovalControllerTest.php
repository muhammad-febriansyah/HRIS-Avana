<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PermissionRequest;
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
 * Create a pending leave request for the seeded tenant.
 */
function makeApprovalLeave(int $tenantId, array $overrides = []): LeaveRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $leaveType = LeaveType::forTenant($tenantId)->firstOrFail();

    return LeaveRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
        'total_days' => 3,
        'reason' => 'Keperluan keluarga',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a pending overtime request for the seeded tenant.
 */
function makeApprovalOvertime(int $tenantId, array $overrides = []): OvertimeRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return OvertimeRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'date' => '2026-07-01',
        'hours' => 3,
        'reason' => 'Deadline proyek',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a pending permission (izin) request for the seeded tenant.
 */
function makeApprovalPermission(int $tenantId, array $overrides = []): PermissionRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return PermissionRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'date' => '2026-07-01',
        'type' => 'izin_jam',
        'start_time' => '10:00',
        'end_time' => '12:00',
        'reason' => 'Urusan pribadi',
        'status' => 'pending',
    ], $overrides));
}

it('renders the approval center with pending, history and counts props', function (): void {
    makeApprovalLeave($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/approval', false)
            ->has('pending')
            ->has('pending.0', fn (Assert $row) => $row
                ->where('type', 'leave')
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('title')
                ->has('detail')
                ->has('reason')
                ->has('requested_at')
                ->has('status')
                ->has('status_label'))
            ->has('history')
            ->has('counts'));
});

it('aggregates pending requests across types with per-type counts', function (): void {
    $baseline = actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->viewData('page')['props']['counts'];

    $leave = makeApprovalLeave($this->tenant->id);
    $overtime = makeApprovalOvertime($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.leave', $baseline['leave'] + 1)
            ->where('counts.lembur', $baseline['lembur'] + 1)
            ->where('counts.total', $baseline['total'] + 2)
            ->where('pending', fn ($pending) => collect($pending)->contains(
                fn ($row) => $row['type'] === 'leave' && $row['id'] === $leave->id,
            ) && collect($pending)->contains(
                fn ($row) => $row['type'] === 'lembur' && $row['id'] === $overtime->id,
            )));
});

it('approves a leave request and decrements the matching balance', function (): void {
    $leave = makeApprovalLeave($this->tenant->id);

    $balance = LeaveBalance::query()
        ->where('employee_id', $leave->employee_id)
        ->where('leave_type_id', $leave->leave_type_id)
        ->where('year', 2026)
        ->firstOrFail();

    $usedBefore = (float) $balance->used;
    $remainingBefore = (float) $balance->remaining;

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $leave->id]))
        ->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe('approved');

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore + 3);
    expect((float) $balance->remaining)->toBe($remainingBefore - 3);
});

it('approves an overtime request', function (): void {
    $overtime = makeApprovalOvertime($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'lembur', 'id' => $overtime->id]))
        ->assertSessionHas('success');

    expect($overtime->fresh()->status)->toBe('approved');
});

it('rejects a permission (izin) request', function (): void {
    $permission = makeApprovalPermission($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.reject', ['type' => 'izin', 'id' => $permission->id]))
        ->assertSessionHas('success');

    expect($permission->fresh()->status)->toBe('rejected');
});

it('returns 404 for an unknown approval type', function (): void {
    $leave = makeApprovalLeave($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'unknown', 'id' => $leave->id]))
        ->assertNotFound();

    expect($leave->fresh()->status)->toBe('pending');
});

it('returns 404 when approving a request from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-approval']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-AP-1',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = LeaveRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'total_days' => 2,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $foreign->id]))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids an employee-role user from viewing the approval center', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.approval'))
        ->assertForbidden();
});
