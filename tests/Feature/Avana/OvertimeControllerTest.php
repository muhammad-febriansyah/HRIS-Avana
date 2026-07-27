<?php

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a pending overtime request for the seeded tenant.
 */
function makeOvertimeRequest(int $tenantId, array $overrides = []): OvertimeRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return OvertimeRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'date' => '2026-07-01',
        'hours' => 2,
        'reason' => 'Deadline proyek',
        'status' => 'pending',
    ], $overrides));
}

it('renders the cuti page with the overtime requests prop', function (): void {
    makeOvertimeRequest($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/cuti/index', false)
            ->has('overtimeRequests')
            ->has('overtimeRequests.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('date')
                ->has('hours')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('permissionRequests')
            ->has('wfhRequests'));
});

it('creates a pending overtime request scoped to the tenant and branch', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'reason' => 'Lembur akhir bulan',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $overtime = OvertimeRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($overtime->tenant_id)->toBe($this->tenant->id);
    expect($overtime->branch_id)->toBe($employee->branch_id);
    expect($overtime->status)->toBe('pending');
    expect((float) $overtime->hours)->toBe(3.0);
});

it('auto-approves overtime submitted by a top approver (director)', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $employee->update(['is_top_approver' => true]);

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'start_time' => '18:00',
            'end_time' => '21:00',
            'reason' => 'Lembur direksi',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $overtime = OvertimeRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail();
    expect($overtime->status)->toBe('approved');
});

it('validates required fields when storing overtime', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => '',
            'date' => '',
            'start_time' => '',
            'end_time' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'date', 'start_time', 'end_time']);
});

it('approves a pending overtime request', function (): void {
    $overtime = makeOvertimeRequest($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.approve', $overtime))
        ->assertSessionHas('success');

    expect($overtime->fresh()->status)->toBe('approved');
});

it('rejects a pending overtime request', function (): void {
    $overtime = makeOvertimeRequest($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.reject', $overtime))
        ->assertSessionHas('success');

    expect($overtime->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving an overtime request from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-ot']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-OT-1',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = OvertimeRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'date' => '2026-07-01',
        'hours' => 2,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids an employee-role user from storing overtime', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($staff)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'start_time' => '18:00',
            'end_time' => '21:00',
        ])
        ->assertForbidden();
});

it('forbids an employee-role user from approving overtime', function (): void {
    $overtime = makeOvertimeRequest($this->tenant->id);

    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->post(route('avana.cuti.lembur.approve', $overtime))
        ->assertForbidden();

    expect($overtime->fresh()->status)->toBe('pending');
});

it('derives the hours from the filed time range', function (): void {
    // The range is what an approver checks, so payroll must agree with it —
    // the client no longer sends a number at all.
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'start_time' => '18:30',
            'end_time' => '21:15',
        ])
        ->assertSessionHas('success');

    $overtime = OvertimeRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect((float) $overtime->hours)->toBe(2.75)
        ->and(substr((string) $overtime->start_time, 0, 5))->toBe('18:30')
        ->and(substr((string) $overtime->end_time, 0, 5))->toBe('21:15');
});

it('counts overtime that runs past midnight as the next day', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'start_time' => '22:00',
            'end_time' => '01:00',
        ])
        ->assertSessionHas('success');

    expect((float) OvertimeRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail()->hours)
        ->toBe(3.0);
});

it('refuses a range too short or too long to be a shift', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    foreach ([['18:00', '18:15'], ['08:00', '21:00'], ['19:00', '19:00']] as [$start, $end]) {
        actingAs($this->admin)
            ->post(route('avana.cuti.lembur.store'), [
                'employee_id' => $employee->id,
                'date' => '2026-07-10',
                'start_time' => $start,
                'end_time' => $end,
            ])
            ->assertSessionHasErrors('end_time');
    }
});

it('ignores an hours value posted alongside the range', function (): void {
    // Trusting a client-sent total would let someone file "18:00–19:00, 8 jam".
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'hours' => 8,
        ])
        ->assertSessionHas('success');

    expect((float) OvertimeRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail()->hours)
        ->toBe(1.0);
});
