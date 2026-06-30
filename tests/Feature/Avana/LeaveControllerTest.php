<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
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
function makeLeaveRequest(int $tenantId, array $overrides = []): LeaveRequest
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

it('renders the paginated cuti index with the expected props', function (): void {
    makeLeaveRequest($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/cuti/index', false)
            ->has('requests.data')
            ->has('requests.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('start_date')
                ->has('end_date')
                ->has('total_days')
                ->has('durasi')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('leaveTypes')
            ->has('employees')
            ->has('balances'));
});

it('only lists leave requests that belong to the current tenant', function (): void {
    makeLeaveRequest($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    LeaveRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'total_days' => 2,
        'status' => 'pending',
    ]);

    $tenantTotal = LeaveRequest::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', $tenantTotal));
});

it('creates a pending leave request on behalf of an employee', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $leaveType = LeaveType::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'reason' => 'Cuti tahunan',
        ])
        ->assertRedirect(route('avana.cuti'))
        ->assertSessionHas('success');

    $leave = LeaveRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($leave->tenant_id)->toBe($this->tenant->id);
    expect($leave->branch_id)->toBe($employee->branch_id);
    expect($leave->status)->toBe('pending');
    expect((int) $leave->total_days)->toBe(3);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.store'), [
            'employee_id' => '',
            'leave_type_id' => '',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-01',
        ])
        ->assertSessionHasErrors(['employee_id', 'leave_type_id', 'end_date']);
});

it('approves a leave request and decrements the matching balance', function (): void {
    $leave = makeLeaveRequest($this->tenant->id);

    $balance = LeaveBalance::query()
        ->where('employee_id', $leave->employee_id)
        ->where('leave_type_id', $leave->leave_type_id)
        ->where('year', 2026)
        ->firstOrFail();

    $usedBefore = (float) $balance->used;
    $remainingBefore = (float) $balance->remaining;

    actingAs($this->admin)
        ->post(route('avana.cuti.approve', $leave))
        ->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe('approved');

    $balance->refresh();
    expect((float) $balance->used)->toBe($usedBefore + 3);
    expect((float) $balance->remaining)->toBe($remainingBefore - 3);
});

it('rejects a leave request', function (): void {
    $leave = makeLeaveRequest($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.cuti.reject', $leave))
        ->assertSessionHas('success');

    expect($leave->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving a leave request from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
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
        ->post(route('avana.cuti.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids users without leave permissions from listing leave requests', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.cuti'))
        ->assertForbidden();
});
