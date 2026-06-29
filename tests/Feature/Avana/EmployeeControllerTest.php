<?php

use App\Models\Branch;
use App\Models\Department;
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

it('renders the paginated employee index with the expected props', function (): void {
    actingAs($this->admin)
        ->get(route('avana.employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/employees/index', false)
            ->has('employees.data')
            ->has('employees.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee_number')
                ->has('full_name')
                ->has('initials')
                ->has('avatar_color')
                ->has('status_label')
                ->has('employment_label')
                ->etc())
            ->has('filters')
            ->has('branches')
            ->has('departments'));
});

it('filters the employee list by search term', function (): void {
    actingAs($this->admin)
        ->get(route('avana.employees.index', ['search' => 'Putri']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.full_name', 'Putri Anjani'));
});

it('only lists employees that belong to the current tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->get(route('avana.employees.index', ['search' => 'Tenant Lain']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('employees.data', 0));
});

it('creates an employee and auto-generates the employee number', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->first();
    $department = Department::forTenant($this->tenant->id)->first();

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'employment_status' => 'contract',
            'status' => 'active',
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('avana.employees.index'))
        ->assertSessionHas('success');

    $employee = Employee::where('full_name', 'Budi Santoso')->firstOrFail();

    expect($employee->tenant_id)->toBe($this->tenant->id);
    expect($employee->employee_number)->toStartWith('EMP-');
});

it('validates required fields and the NIK format on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => '',
            'employment_status' => 'invalid',
            'nik' => '123',
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['full_name', 'employment_status', 'nik']);
});

it('updates an existing employee', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.employees.update', $employee), [
            'full_name' => 'Nama Diperbarui',
            'employment_status' => $employee->employment_status,
            'status' => 'inactive',
        ])
        ->assertRedirect(route('avana.employees.index'))
        ->assertSessionHas('success');

    expect($employee->fresh()->full_name)->toBe('Nama Diperbarui');
    expect($employee->fresh()->status)->toBe('inactive');
});

it('soft deletes an employee on destroy', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->delete(route('avana.employees.destroy', $employee))
        ->assertSessionHas('success');

    expect(Employee::withTrashed()->find($employee->id)->trashed())->toBeTrue();
});

it('returns 404 when accessing an employee from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->get(route('avana.employees.show', $foreign))
        ->assertNotFound();
});

it('forbids users without employee permissions from listing employees', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.employees.index'))
        ->assertForbidden();
});
