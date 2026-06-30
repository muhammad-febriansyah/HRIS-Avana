<?php

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();
});

/**
 * Create a contract for the demo tenant's first employee.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeContract(array $attributes = []): EmployeeContract
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    /** @var Employee $employee */
    $employee = test()->employee;

    return EmployeeContract::create(array_merge([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'contract_number' => 'PKWT-'.fake()->unique()->numerify('####'),
        'contract_type' => 'pkwt',
        'start_date' => Carbon::today()->subMonths(2)->toDateString(),
        'end_date' => Carbon::today()->addMonths(6)->toDateString(),
        'basic_salary' => 7500000,
        'status' => 'active',
    ], $attributes));
}

it('renders the kontrak index with contracts and employees props', function (): void {
    makeContract(['contract_number' => 'PKWT-INDEX-1']);

    actingAs($this->admin)
        ->get(route('avana.kontrak'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kontrak/index', false)
            ->has('contracts.data')
            ->has('contracts.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('contract_number')
                ->has('employee')
                ->has('contract_type')
                ->has('start_date')
                ->has('end_date')
                ->has('basic_salary')
                ->has('status')
                ->has('expiring_soon')
                ->has('days_to_expiry')
                ->etc())
            ->has('employees')
            ->has('stats')
            ->has('filters'));
});

it('only lists contracts that belong to the current tenant', function (): void {
    makeContract(['contract_number' => 'PKWT-MINE']);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    EmployeeContract::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'contract_number' => 'PKWT-ASING',
        'contract_type' => 'pkwt',
        'start_date' => Carbon::today()->toDateString(),
        'basic_salary' => 1000000,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->get(route('avana.kontrak'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('contracts.meta.total', 1));
});

it('creates a contract scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kontrak.store'), [
            'employee_id' => $this->employee->id,
            'contract_number' => 'PKWT-2026-100',
            'contract_type' => 'pkwt',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'basic_salary' => 8000000,
            'status' => 'active',
            'notes' => 'Kontrak baru',
        ])
        ->assertRedirect(route('avana.kontrak'))
        ->assertSessionHas('success');

    $contract = EmployeeContract::where('contract_number', 'PKWT-2026-100')->firstOrFail();

    expect($contract->tenant_id)->toBe($this->tenant->id);
    expect($contract->employee_id)->toBe($this->employee->id);
    expect($contract->status)->toBe('active');
    expect((float) $contract->basic_salary)->toBe(8000000.0);
});

it('validates required fields and duplicate contract number on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kontrak.store'), [
            'employee_id' => '',
            'contract_number' => '',
            'contract_type' => '',
            'start_date' => '',
            'basic_salary' => '',
            'status' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'contract_number', 'contract_type', 'start_date', 'basic_salary', 'status']);

    makeContract(['contract_number' => 'PKWT-DUP']);

    actingAs($this->admin)
        ->post(route('avana.kontrak.store'), [
            'employee_id' => $this->employee->id,
            'contract_number' => 'PKWT-DUP',
            'contract_type' => 'pkwt',
            'start_date' => '2026-01-01',
            'basic_salary' => 5000000,
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['contract_number']);
});

it('rejects an end date before the start date', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kontrak.store'), [
            'employee_id' => $this->employee->id,
            'contract_number' => 'PKWT-BADRANGE',
            'contract_type' => 'pkwt',
            'start_date' => '2026-06-01',
            'end_date' => '2026-05-01',
            'basic_salary' => 5000000,
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['end_date']);
});

it('updates an existing contract', function (): void {
    $contract = makeContract(['contract_number' => 'PKWT-EDIT', 'status' => 'active']);

    actingAs($this->admin)
        ->put(route('avana.kontrak.update', $contract), [
            'employee_id' => $this->employee->id,
            'contract_number' => 'PKWT-EDIT',
            'contract_type' => 'pkwtt',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'basic_salary' => 9000000,
            'status' => 'terminated',
        ])
        ->assertRedirect(route('avana.kontrak'))
        ->assertSessionHas('success');

    $contract->refresh();

    expect($contract->contract_type)->toBe('pkwtt');
    expect($contract->status)->toBe('terminated');
    expect((float) $contract->basic_salary)->toBe(9000000.0);
    expect($contract->end_date)->toBeNull();
});

it('returns 404 when updating a contract from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-8888',
        'full_name' => 'Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = EmployeeContract::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'contract_number' => 'PKWT-FOREIGN',
        'contract_type' => 'pkwt',
        'start_date' => Carbon::today()->toDateString(),
        'basic_salary' => 1000000,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.kontrak.update', $foreign), [
            'employee_id' => $foreignEmployee->id,
            'contract_number' => 'PKWT-HACK',
            'contract_type' => 'pkwt',
            'start_date' => '2026-01-01',
            'basic_salary' => 1000000,
            'status' => 'active',
        ])
        ->assertNotFound();
});

it('deletes a contract', function (): void {
    $contract = makeContract(['contract_number' => 'PKWT-DEL']);

    actingAs($this->admin)
        ->delete(route('avana.kontrak.destroy', $contract))
        ->assertSessionHas('success');

    expect(EmployeeContract::find($contract->id))->toBeNull();
});

it('returns 404 when deleting a contract from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = EmployeeContract::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'contract_number' => 'PKWT-FOREIGN-DEL',
        'contract_type' => 'pkwt',
        'start_date' => Carbon::today()->toDateString(),
        'basic_salary' => 1000000,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.kontrak.destroy', $foreign))
        ->assertNotFound();

    expect(EmployeeContract::find($foreign->id))->not->toBeNull();
});

it('flags a contract ending within 30 days as expiring soon', function (): void {
    makeContract([
        'contract_number' => 'PKWT-SOON',
        'status' => 'active',
        'end_date' => Carbon::today()->addDays(10)->toDateString(),
    ]);

    actingAs($this->admin)
        ->get(route('avana.kontrak'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contracts.data', fn ($rows) => collect($rows)
                ->firstWhere('contract_number', 'PKWT-SOON')['expiring_soon'] === true)
            ->where('stats.expiring_soon', 1));
});

it('does not flag a contract ending beyond 30 days as expiring soon', function (): void {
    makeContract([
        'contract_number' => 'PKWT-FAR',
        'status' => 'active',
        'end_date' => Carbon::today()->addDays(90)->toDateString(),
    ]);

    actingAs($this->admin)
        ->get(route('avana.kontrak'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('contracts.data', fn ($rows) => collect($rows)
                ->firstWhere('contract_number', 'PKWT-FAR')['expiring_soon'] === false));
});

it('forbids users without employee permissions from managing contracts', function (): void {
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$this->employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.kontrak'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.kontrak.store'), [
            'employee_id' => $this->employee->id,
            'contract_number' => 'PKWT-NOPE',
            'contract_type' => 'pkwt',
            'start_date' => '2026-01-01',
            'basic_salary' => 5000000,
            'status' => 'active',
        ])
        ->assertForbidden();
});
