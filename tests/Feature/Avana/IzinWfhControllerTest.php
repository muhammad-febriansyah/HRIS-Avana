<?php

use App\Models\Employee;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WfhRequest;
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
 * Create a pending izin (permission) request for the seeded tenant.
 */
function makePermissionRequest(int $tenantId, array $overrides = []): PermissionRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return PermissionRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'date' => '2026-07-01',
        'type' => 'izin_jam',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'reason' => 'Urusan pribadi',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a pending WFH request for the seeded tenant.
 */
function makeWfhRequest(int $tenantId, array $overrides = []): WfhRequest
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return WfhRequest::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'reason' => 'Bekerja dari rumah',
        'status' => 'pending',
    ], $overrides));
}

/**
 * Build an employee-role user under the given tenant.
 */
function employeeRoleUser(int $tenantId): User
{
    $role = Role::where('tenant_id', $tenantId)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $tenantId]);
    $staff->roles()->sync([$role->id]);

    return $staff;
}

it('renders the cuti page with izin and wfh props', function (): void {
    makePermissionRequest($this->tenant->id);
    makeWfhRequest($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/cuti/index', false)
            ->has('permissionRequests.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('date')
                ->has('type')
                ->has('start_time')
                ->has('end_time')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('wfhRequests.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('start_date')
                ->has('end_date')
                ->has('status')
                ->has('status_label')
                ->etc()));
});

/* ---------------- Izin (permission) ---------------- */

it('creates a pending izin request scoped to the tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.izin.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'type' => 'keluar_kantor',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'reason' => 'Meeting klien',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $izin = PermissionRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($izin->tenant_id)->toBe($this->tenant->id);
    expect($izin->type)->toBe('keluar_kantor');
    expect($izin->status)->toBe('pending');
});

it('validates required fields when storing izin', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.izin.store'), [
            'employee_id' => '',
            'date' => '',
            'type' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'date', 'type']);
});

it('approves and rejects an izin request', function (): void {
    $izin = makePermissionRequest($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.cuti.izin.approve', $izin))
        ->assertSessionHas('success');
    expect($izin->fresh()->status)->toBe('approved');

    $other = makePermissionRequest($this->tenant->id);
    actingAs($this->admin)
        ->post(route('avana.cuti.izin.reject', $other))
        ->assertSessionHas('success');
    expect($other->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving an izin request from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-izin']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-IZ-1',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = PermissionRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'date' => '2026-07-01',
        'type' => 'izin_jam',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.cuti.izin.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids an employee-role user from storing and approving izin', function (): void {
    $staff = employeeRoleUser($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $izin = makePermissionRequest($this->tenant->id);

    actingAs($staff)
        ->post(route('avana.cuti.izin.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-07-10',
            'type' => 'izin_jam',
        ])
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.cuti.izin.approve', $izin))
        ->assertForbidden();
});

/* ---------------- WFH ---------------- */

it('creates a pending wfh request scoped to the tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'reason' => 'WFH',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $wfh = WfhRequest::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($wfh->tenant_id)->toBe($this->tenant->id);
    expect($wfh->status)->toBe('pending');
});

it('validates required fields when storing wfh', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => '',
            'start_date' => '2026-07-12',
            'end_date' => '2026-07-01',
        ])
        ->assertSessionHasErrors(['employee_id', 'end_date']);
});

it('approves and rejects a wfh request', function (): void {
    $wfh = makeWfhRequest($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.cuti.wfh.approve', $wfh))
        ->assertSessionHas('success');
    expect($wfh->fresh()->status)->toBe('approved');

    $other = makeWfhRequest($this->tenant->id);
    actingAs($this->admin)
        ->post(route('avana.cuti.wfh.reject', $other))
        ->assertSessionHas('success');
    expect($other->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving a wfh request from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-wfh']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-WF-1',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = WfhRequest::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.cuti.wfh.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids an employee-role user from storing and approving wfh', function (): void {
    $staff = employeeRoleUser($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $wfh = makeWfhRequest($this->tenant->id);

    actingAs($staff)
        ->post(route('avana.cuti.wfh.store'), [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
        ])
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.cuti.wfh.approve', $wfh))
        ->assertForbidden();
});
