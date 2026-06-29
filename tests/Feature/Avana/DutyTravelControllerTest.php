<?php

use App\Models\DutyTravel;
use App\Models\Employee;
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
 * Create a pending duty travel for the seeded tenant.
 */
function makeDutyTravel(int $tenantId, array $overrides = []): DutyTravel
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return DutyTravel::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'destination' => 'Surabaya',
        'purpose' => 'Kunjungan klien',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-03',
        'transport' => 'Pesawat',
        'estimated_cost' => 1500000,
        'per_diem' => 500000,
        'status' => 'pending',
    ], $overrides));
}

it('renders the paginated dinas index with the expected props', function (): void {
    makeDutyTravel($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.dinas'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/dinas', false)
            ->has('travels.data')
            ->has('travels.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('destination')
                ->has('purpose')
                ->has('start_date')
                ->has('end_date')
                ->has('days')
                ->has('transport')
                ->has('estimated_cost')
                ->has('per_diem')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('employees'));
});

it('only lists duty travels that belong to the current tenant', function (): void {
    makeDutyTravel($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    DutyTravel::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'destination' => 'Bandung',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'status' => 'pending',
    ]);

    $tenantTotal = DutyTravel::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.dinas'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('travels.meta.total', $tenantTotal));
});

it('creates a pending duty travel on behalf of an employee', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.dinas.store'), [
            'employee_id' => $employee->id,
            'destination' => 'Jakarta',
            'purpose' => 'Rapat koordinasi',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12',
            'transport' => 'Kereta',
            'estimated_cost' => 2000000,
            'per_diem' => 750000,
        ])
        ->assertRedirect(route('avana.dinas'))
        ->assertSessionHas('success');

    $travel = DutyTravel::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($travel->tenant_id)->toBe($this->tenant->id);
    expect($travel->destination)->toBe('Jakarta');
    expect($travel->status)->toBe('pending');
    expect((float) $travel->estimated_cost)->toBe(2000000.0);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.dinas.store'), [
            'employee_id' => '',
            'destination' => '',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-01',
            'estimated_cost' => -5,
        ])
        ->assertSessionHasErrors(['employee_id', 'destination', 'end_date', 'estimated_cost']);
});

it('approves a duty travel and records the approver', function (): void {
    $travel = makeDutyTravel($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.dinas.approve', $travel))
        ->assertSessionHas('success');

    $travel->refresh();

    expect($travel->status)->toBe('approved');
    expect((int) $travel->approved_by)->toBe($this->admin->id);
});

it('rejects a duty travel', function (): void {
    $travel = makeDutyTravel($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.dinas.reject', $travel))
        ->assertSessionHas('success');

    expect($travel->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving a duty travel from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = DutyTravel::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'destination' => 'Medan',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.dinas.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids plain employees from listing duty travels', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.dinas'))
        ->assertForbidden();
});
