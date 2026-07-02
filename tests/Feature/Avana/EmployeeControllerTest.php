<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;
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

it('assigns a tenant work location to the employee on store', function (): void {
    $workLocation = WorkLocation::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Dewi Lokasi',
            'employment_status' => 'permanent',
            'status' => 'active',
            'branch_id' => $workLocation->branch_id,
            'work_location_id' => $workLocation->id,
        ])
        ->assertRedirect(route('avana.employees.index'))
        ->assertSessionHas('success');

    expect(Employee::where('full_name', 'Dewi Lokasi')->firstOrFail()->work_location_id)
        ->toBe($workLocation->id);
});

it('rejects a work location that belongs to another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Seberang', 'slug' => 'pt-seberang-wl']);
    $foreignBranch = Branch::create(['tenant_id' => $otherTenant->id, 'code' => 'XX', 'name' => 'Cabang X', 'status' => 'active']);
    $foreignLocation = WorkLocation::create([
        'tenant_id' => $otherTenant->id,
        'branch_id' => $foreignBranch->id,
        'name' => 'Lokasi Asing',
        'latitude' => -6.9,
        'longitude' => 107.6,
        'radius_meter' => 100,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Bocor Lokasi',
            'employment_status' => 'permanent',
            'status' => 'active',
            'work_location_id' => $foreignLocation->id,
        ])
        ->assertSessionHasErrors('work_location_id');

    expect(Employee::where('full_name', 'Bocor Lokasi')->exists())->toBeFalse();
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

it('bulk-creates multiple employees in one submit', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->first();

    actingAs($this->admin)
        ->post(route('avana.employees.bulk.store'), [
            'employees' => [
                ['full_name' => 'Massal Satu', 'employment_status' => 'permanent', 'status' => 'active', 'branch_id' => $branch->id],
                ['full_name' => 'Massal Dua', 'employment_status' => 'contract', 'status' => 'active'],
            ],
        ])
        ->assertRedirect(route('avana.employees.index'))
        ->assertSessionHas('success');

    $numbers = Employee::whereIn('full_name', ['Massal Satu', 'Massal Dua'])->pluck('employee_number');

    expect($numbers)->toHaveCount(2);
    expect($numbers->unique())->toHaveCount(2);
    expect($numbers->every(fn (string $n): bool => str_starts_with($n, 'EMP-')))->toBeTrue();
});

it('creates no employee when any bulk row is invalid', function (): void {
    actingAs($this->admin)
        ->post(route('avana.employees.bulk.store'), [
            'employees' => [
                ['full_name' => 'Valid Row', 'employment_status' => 'permanent', 'status' => 'active'],
                ['full_name' => '', 'employment_status' => 'permanent', 'status' => 'active'],
            ],
        ])
        ->assertSessionHasErrors('employees.1.full_name');

    expect(Employee::where('full_name', 'Valid Row')->exists())->toBeFalse();
});

it('rejects a bulk work location from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Beda', 'slug' => 'pt-beda-bulk']);
    $branch = Branch::create(['tenant_id' => $otherTenant->id, 'code' => 'ZZ', 'name' => 'Z', 'status' => 'active']);
    $foreign = WorkLocation::create([
        'tenant_id' => $otherTenant->id, 'branch_id' => $branch->id, 'name' => 'WL Beda',
        'latitude' => -6.1, 'longitude' => 106.1, 'radius_meter' => 100, 'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.bulk.store'), [
            'employees' => [
                ['full_name' => 'Row X', 'employment_status' => 'permanent', 'status' => 'active', 'work_location_id' => $foreign->id],
            ],
        ])
        ->assertSessionHasErrors('employees.0.work_location_id');

    expect(Employee::where('full_name', 'Row X')->exists())->toBeFalse();
});

it('creates a mobile login account when a password is set on store', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->first();

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Login User',
            'email' => 'login.user@avanahr.test',
            'employment_status' => 'permanent',
            'status' => 'active',
            'branch_id' => $branch->id,
            'password' => 'rahasia123',
        ])
        ->assertRedirect(route('avana.employees.index'));

    $employee = Employee::where('full_name', 'Login User')->firstOrFail();
    expect($employee->user_id)->not->toBeNull();
    expect($employee->user->roles->pluck('code'))->toContain('employee');

    // The account authenticates against the mobile API end-to-end.
    $this->postJson('/api/v1/auth/login', [
        'email' => 'login.user@avanahr.test',
        'password' => 'rahasia123',
    ])->assertOk()->assertJsonStructure(['access_token']);
});

it('requires an email to create a login on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'No Email Login',
            'employment_status' => 'permanent',
            'status' => 'active',
            'password' => 'rahasia123',
        ])
        ->assertSessionHasErrors('email');

    expect(Employee::where('full_name', 'No Email Login')->exists())->toBeFalse();
});

it('resets an existing login password on update', function (): void {
    actingAs($this->admin)->post(route('avana.employees.store'), [
        'full_name' => 'Reset Target',
        'email' => 'reset.target@avanahr.test',
        'employment_status' => 'permanent',
        'status' => 'active',
        'password' => 'lamalama1',
    ]);

    $employee = Employee::where('full_name', 'Reset Target')->firstOrFail();

    actingAs($this->admin)->put(route('avana.employees.update', $employee), [
        'full_name' => $employee->full_name,
        'email' => $employee->email,
        'employment_status' => $employee->employment_status,
        'status' => 'active',
        'password' => 'barubaru99',
    ])->assertRedirect(route('avana.employees.index'));

    expect(Hash::check('barubaru99', $employee->user->fresh()->password))->toBeTrue();
});

it('creates login accounts within a bulk submit', function (): void {
    actingAs($this->admin)->post(route('avana.employees.bulk.store'), [
        'employees' => [
            ['full_name' => 'Bulk Login A', 'email' => 'bulk.a@avanahr.test', 'employment_status' => 'permanent', 'status' => 'active', 'password' => 'rahasia123'],
            ['full_name' => 'Bulk NoLogin', 'employment_status' => 'contract', 'status' => 'active'],
        ],
    ])->assertRedirect(route('avana.employees.index'));

    expect(Employee::where('full_name', 'Bulk Login A')->firstOrFail()->user_id)->not->toBeNull();
    expect(Employee::where('full_name', 'Bulk NoLogin')->firstOrFail()->user_id)->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'bulk.a@avanahr.test', 'password' => 'rahasia123'])
        ->assertOk()->assertJsonStructure(['access_token']);
});

it('rejects a bulk row that sets a password without an email', function (): void {
    actingAs($this->admin)->post(route('avana.employees.bulk.store'), [
        'employees' => [
            ['full_name' => 'No Email Bulk', 'employment_status' => 'permanent', 'status' => 'active', 'password' => 'rahasia123'],
        ],
    ])->assertSessionHasErrors('employees.0.email');

    expect(Employee::where('full_name', 'No Email Bulk')->exists())->toBeFalse();
});

it('rejects duplicate login emails within a bulk submit', function (): void {
    actingAs($this->admin)->post(route('avana.employees.bulk.store'), [
        'employees' => [
            ['full_name' => 'Dup One', 'email' => 'dup@avanahr.test', 'employment_status' => 'permanent', 'status' => 'active', 'password' => 'rahasia123'],
            ['full_name' => 'Dup Two', 'email' => 'dup@avanahr.test', 'employment_status' => 'permanent', 'status' => 'active', 'password' => 'rahasia123'],
        ],
    ])->assertSessionHasErrors('employees.1.email');

    expect(Employee::where('full_name', 'Dup One')->exists())->toBeFalse();
});
