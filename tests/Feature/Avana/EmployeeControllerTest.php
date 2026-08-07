<?php

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WorkLocation;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
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

it('soft deletes several employees on bulk destroy', function (): void {
    $ids = Employee::forTenant($this->tenant->id)->take(3)->pluck('id')->all();

    actingAs($this->admin)
        ->delete(route('avana.employees.bulk-destroy'), ['ids' => $ids])
        ->assertSessionHas('success');

    expect(Employee::onlyTrashed()->whereIn('id', $ids)->count())->toBe(count($ids));
});

it('does not bulk delete employees from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreign = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Orang Luar',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.employees.bulk-destroy'), ['ids' => [$foreign->id]])
        ->assertSessionHas('success');

    expect(Employee::withTrashed()->find($foreign->id)->trashed())->toBeFalse();
});

it('validates that bulk destroy requires at least one id', function (): void {
    actingAs($this->admin)
        ->delete(route('avana.employees.bulk-destroy'), ['ids' => []])
        ->assertSessionHasErrors('ids');
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

it('exposes only the actively-held assets on the employee detail page', function (): void {
    $employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();

    $held = Asset::factory()->create([
        'tenant_id' => $this->tenant->id,
        'code' => 'AST-HELD',
        'name' => 'Laptop Uji',
        'condition' => 'good',
        'status' => 'assigned',
    ]);
    AssetAssignment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'asset_id' => $held->id,
        'employee_id' => $employee->id,
        'returned_date' => null,
    ]);

    // A returned assignment must not surface as a currently-held asset.
    $returned = Asset::factory()->create(['tenant_id' => $this->tenant->id]);
    AssetAssignment::factory()->returned()->create([
        'tenant_id' => $this->tenant->id,
        'asset_id' => $returned->id,
        'employee_id' => $employee->id,
    ]);

    actingAs($this->admin)
        ->get(route('avana.employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/employees/show', false)
            ->has('employee.data.held_assets', 1)
            ->where('employee.data.held_assets.0.asset.code', 'AST-HELD')
            ->where('employee.data.held_assets.0.asset.condition_label', 'Baik'));
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
    $role = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Login User',
            'email' => 'login.user@avanahr.test',
            'employment_status' => 'permanent',
            'status' => 'active',
            'branch_id' => $branch->id,
            'role_id' => $role->id,
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

it('assigns the chosen role to the login account on store', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->first();
    $itRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Staff IT',
        'code' => 'staff_it',
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'IT Person',
            'email' => 'it.person@avanahr.test',
            'employment_status' => 'permanent',
            'status' => 'active',
            'branch_id' => $branch->id,
            'password' => 'rahasia123',
            'role_id' => $itRole->id,
        ])
        ->assertRedirect(route('avana.employees.index'));

    $employee = Employee::where('full_name', 'IT Person')->firstOrFail();
    expect($employee->user->roles->pluck('code'))->toContain('staff_it');
    // The chosen role replaces the default, it does not stack with 'employee'.
    expect($employee->user->roles)->toHaveCount(1);
});

it('changes an existing account role on update without a password', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->first();
    $itRole = Role::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Staff IT',
        'code' => 'staff_it',
    ]);

    actingAs($this->admin)->post(route('avana.employees.store'), [
        'full_name' => 'Role Switch',
        'email' => 'role.switch@avanahr.test',
        'employment_status' => 'permanent',
        'status' => 'active',
        'branch_id' => $branch->id,
        'role_id' => Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->value('id'),
        'password' => 'rahasia123',
    ]);

    $employee = Employee::where('full_name', 'Role Switch')->firstOrFail();
    expect($employee->user->roles->pluck('code'))->toContain('employee');

    actingAs($this->admin)->put(route('avana.employees.update', $employee), [
        'full_name' => $employee->full_name,
        'email' => $employee->email,
        'employment_status' => $employee->employment_status,
        'status' => 'active',
        'role_id' => $itRole->id,
    ])->assertRedirect(route('avana.employees.index'));

    $roles = $employee->user->fresh()->roles->pluck('code');
    expect($roles)->toContain('staff_it');
    expect($roles)->not->toContain('employee');
});

it('links an existing account to an employee that has none', function (): void {
    // The admin's own login: made outside the Karyawan form, so no employee
    // sits behind it and every mobile self-service call answers 403.
    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Rina Admin',
        'employee_number' => 'EMP-LINK-1',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    expect($this->admin->employee)->toBeNull();

    actingAs($this->admin)
        ->put(route('avana.employees.update', $employee), [
            'full_name' => $employee->full_name,
            'employment_status' => 'permanent',
            'status' => 'active',
            'link_user_id' => $this->admin->id,
        ])
        ->assertRedirect(route('avana.employees.index'));

    expect($employee->fresh()->user_id)->toBe($this->admin->id);

    // What the link is for: the mobile profile now resolves instead of 403.
    $token = auth('api')->login($this->admin->fresh());

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/me/profile')
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Rina Admin');
});

it('refuses to link an account that already belongs to another employee', function (): void {
    $taken = Employee::forTenant($this->tenant->id)->whereNotNull('user_id')->firstOrFail();

    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Kandidat Tautan',
        'employee_number' => 'EMP-LINK-2',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $employee), [
            'full_name' => $employee->full_name,
            'employment_status' => 'permanent',
            'status' => 'active',
            'link_user_id' => $taken->user_id,
        ])
        ->assertSessionHasErrors('link_user_id');

    expect($employee->fresh()->user_id)->toBeNull();
});

it('refuses to link an account from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Seberang', 'slug' => 'pt-seberang-link']);
    $outsider = User::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Orang Luar',
        'email' => 'orang.luar@avanahr.test',
        'password' => 'rahasia123',
        'status' => 'active',
    ]);

    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Kandidat Seberang',
        'employee_number' => 'EMP-LINK-3',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $employee), [
            'full_name' => $employee->full_name,
            'employment_status' => 'permanent',
            'status' => 'active',
            'link_user_id' => $outsider->id,
        ])
        ->assertSessionHasErrors('link_user_id');

    expect($employee->fresh()->user_id)->toBeNull();
});

it('rejects a password alongside a linked account', function (): void {
    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Dua Jalan',
        'employee_number' => 'EMP-LINK-4',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $employee), [
            'full_name' => $employee->full_name,
            'employment_status' => 'permanent',
            'status' => 'active',
            'link_user_id' => $this->admin->id,
            'password' => 'rahasia123',
        ])
        ->assertSessionHasErrors('password');

    expect($employee->fresh()->user_id)->toBeNull();
});

it('offers only unlinked tenant accounts to the employee form', function (): void {
    actingAs($this->admin)
        ->get(route('avana.employees.create'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $ids = collect($page->toArray()['props']['options']['linkableUsers'])->pluck('id');

            expect($ids)->toContain($this->admin->id);

            $linkedUserIds = Employee::forTenant($this->tenant->id)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            expect($ids->intersect($linkedUserIds))->toBeEmpty();
        });
});

it('persists the top-approver flag from the employee form', function (): void {
    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Direktur Utama',
            'employment_status' => 'permanent',
            'status' => 'active',
            'is_top_approver' => true,
        ])
        ->assertRedirect(route('avana.employees.index'));

    $employee = Employee::where('full_name', 'Direktur Utama')->firstOrFail();
    expect($employee->is_top_approver)->toBeTrue();
});

it('rejects a role from another tenant on store', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-role']);
    $foreignRole = Role::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Outsider',
        'code' => 'outsider',
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Cross Tenant',
            'email' => 'cross.tenant@avanahr.test',
            'employment_status' => 'permanent',
            'status' => 'active',
            'password' => 'rahasia123',
            'role_id' => $foreignRole->id,
        ])
        ->assertSessionHasErrors('role_id');
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
        'role_id' => Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->value('id'),
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
    $role = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    actingAs($this->admin)->post(route('avana.employees.bulk.store'), [
        'role_id' => $role->id,
        'employees' => [
            ['full_name' => 'Bulk Login A', 'email' => 'bulk.a@avanahr.test', 'employment_status' => 'permanent', 'status' => 'active', 'password' => 'rahasia123'],
            ['full_name' => 'Bulk NoLogin', 'employment_status' => 'contract', 'status' => 'active'],
        ],
    ])->assertRedirect(route('avana.employees.index'));

    $withLogin = Employee::where('full_name', 'Bulk Login A')->firstOrFail();

    expect($withLogin->user_id)->not->toBeNull();
    expect($withLogin->user->roles->pluck('id'))->toContain($role->id);
    expect(Employee::where('full_name', 'Bulk NoLogin')->firstOrFail()->user_id)->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'bulk.a@avanahr.test', 'password' => 'rahasia123'])
        ->assertOk()->assertJsonStructure(['access_token']);
});

it('refuses a bulk login batch with no role picked', function (): void {
    actingAs($this->admin)->post(route('avana.employees.bulk.store'), [
        'employees' => [
            ['full_name' => 'Bulk NoRole', 'email' => 'bulk.norole@avanahr.test', 'employment_status' => 'permanent', 'status' => 'active', 'password' => 'rahasia123'],
        ],
    ])->assertSessionHasErrors('role_id');

    expect(Employee::where('full_name', 'Bulk NoRole')->exists())->toBeFalse();
});

it('allows a bulk batch with no role when no row creates a login', function (): void {
    actingAs($this->admin)->post(route('avana.employees.bulk.store'), [
        'employees' => [
            ['full_name' => 'Bulk Plain', 'employment_status' => 'contract', 'status' => 'active'],
        ],
    ])->assertRedirect(route('avana.employees.index'));

    expect(Employee::where('full_name', 'Bulk Plain')->firstOrFail()->user_id)->toBeNull();
});

it('refuses to create a login on the employee form with no role picked', function (): void {
    actingAs($this->admin)->post(route('avana.employees.store'), [
        'full_name' => 'No Role Login',
        'email' => 'norole.login@avanahr.test',
        'employment_status' => 'permanent',
        'status' => 'active',
        'password' => 'rahasia123',
    ])->assertSessionHasErrors('role_id');

    expect(Employee::where('full_name', 'No Role Login')->exists())->toBeFalse();
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

/** Create a tenant-1 employee optionally linked to a login account. */
function makeEmployeeWithLogin(int $tenantId, string $number, ?User $user): Employee
{
    return Employee::create([
        'tenant_id' => $tenantId,
        'user_id' => $user?->id,
        'employee_number' => $number,
        'full_name' => 'Test '.$number,
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
}

it('resets an employee bound device so a new phone can sign in', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $employee = makeEmployeeWithLogin($this->tenant->id, 'EMP-RD-1', $user);
    $device = UserDevice::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
        'device_id' => 'device-abc',
        'status' => 'active',
        'bound_at' => now(),
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.reset-device', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($device->fresh()->status)->toBe('reset');
});

it('resets a device bound to the email-matched login even without a direct user link', function (): void {
    // The mobile app binds by email; the employee row may not point at that
    // exact users row. Reset must still release the device.
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'seam.device@nusantara.test',
        'status' => 'active',
    ]);
    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => null,
        'email' => 'seam.device@nusantara.test',
        'employee_number' => 'EMP-SEAM-1',
        'full_name' => 'Seam Device',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $device = UserDevice::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $user->id,
        'device_id' => 'seam-device-xyz',
        'status' => 'active',
        'bound_at' => now(),
    ]);

    actingAs($this->admin)
        ->post(route('avana.employees.reset-device', $employee))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($device->fresh()->status)->toBe('reset');
});

it('toggles an employee login account between active and inactive', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);
    $employee = makeEmployeeWithLogin($this->tenant->id, 'EMP-TA-1', $user);

    actingAs($this->admin)->post(route('avana.employees.toggle-account', $employee))->assertRedirect();
    expect($user->fresh()->status)->toBe('inactive');

    actingAs($this->admin)->post(route('avana.employees.toggle-account', $employee));
    expect($user->fresh()->status)->toBe('active');
});

it('reports an error when the employee has no login account', function (): void {
    $employee = makeEmployeeWithLogin($this->tenant->id, 'EMP-NL-1', null);

    actingAs($this->admin)
        ->post(route('avana.employees.reset-device', $employee))
        ->assertSessionHas('error');
});

it('rejects account actions on an employee from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain Akun', 'slug' => 'pt-lain-akun']);
    $employee = makeEmployeeWithLogin($otherTenant->id, 'EMP-XT-1', null);

    actingAs($this->admin)
        ->post(route('avana.employees.reset-device', $employee))
        ->assertStatus(404);
});

it('turns the "Tidak ada — Approver Puncak" choice into a director', function (): void {
    // This is what the form actually posts; the earlier test sets the flag by
    // hand and so never exercised the sentinel the UI sends.
    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Hendra Direksi',
            'employment_status' => 'permanent',
            'status' => 'active',
            'manager_id' => 'none',
        ])
        ->assertRedirect(route('avana.employees.index'));

    $employee = Employee::where('full_name', 'Hendra Direksi')->firstOrFail();

    expect($employee->is_top_approver)->toBeTrue()
        ->and($employee->manager_id)->toBeNull();
});

it('rearranges the reporting line straight from the org chart', function (): void {
    $staff = Employee::forTenant($this->tenant->id)->firstOrFail();
    $boss = Employee::forTenant($this->tenant->id)->whereKeyNot($staff->id)->firstOrFail();

    // The seeded demo already has a hierarchy; detach both so the assertion is
    // about this request and not about whatever line they started on.
    $staff->update(['manager_id' => null]);
    $boss->update(['manager_id' => null]);

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $staff), ['manager_id' => (string) $boss->id])
        ->assertSessionHas('success');

    expect($staff->fresh()->manager_id)->toBe($boss->id)
        ->and($staff->fresh()->is_top_approver)->toBeFalse();
});

it('takes both sentinels from the org chart too', function (): void {
    $staff = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $staff), ['manager_id' => 'none'])
        ->assertSessionHas('success');

    expect($staff->fresh())
        ->manager_id->toBeNull()
        ->is_top_approver->toBeTrue();

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $staff), ['manager_id' => 'unassigned'])
        ->assertSessionHas('success');

    expect($staff->fresh())
        ->manager_id->toBeNull()
        ->is_top_approver->toBeFalse();
});

it('swaps two people when pointed at a subordinate', function (): void {
    // boss → deputy, plus a peer who stays put. Promoting the deputy over the
    // boss is a single choice: the deputy takes the boss's old place.
    $boss = Employee::forTenant($this->tenant->id)->firstOrFail();
    $deputy = Employee::forTenant($this->tenant->id)->whereKeyNot($boss->id)->firstOrFail();
    $peer = Employee::forTenant($this->tenant->id)->whereKeyNot([$boss->id, $deputy->id])->firstOrFail();

    $boss->update(['manager_id' => null, 'is_top_approver' => true]);
    $deputy->update(['manager_id' => $boss->id, 'is_top_approver' => false]);
    $peer->update(['manager_id' => $boss->id, 'is_top_approver' => false]);

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $boss), ['manager_id' => (string) $deputy->id])
        ->assertSessionHas('success');

    expect($deputy->fresh())
        ->manager_id->toBeNull()
        ->is_top_approver->toBeTrue();

    expect($boss->fresh())
        ->manager_id->toBe($deputy->id)
        ->is_top_approver->toBeFalse();

    // The boss's other reports follow the boss down rather than being orphaned.
    expect($peer->fresh()->manager_id)->toBe($boss->id);
});

it('swaps across several levels without looping the chain', function (): void {
    // head → middle → junior, then point head at junior. Junior must land in
    // head's old slot, leaving head under junior and middle under head.
    $head = Employee::forTenant($this->tenant->id)->firstOrFail();
    $middle = Employee::forTenant($this->tenant->id)->whereKeyNot($head->id)->firstOrFail();
    $junior = Employee::forTenant($this->tenant->id)->whereKeyNot([$head->id, $middle->id])->firstOrFail();

    $head->update(['manager_id' => null, 'is_top_approver' => false]);
    $middle->update(['manager_id' => $head->id]);
    $junior->update(['manager_id' => $middle->id]);

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $head), ['manager_id' => (string) $junior->id])
        ->assertSessionHas('success');

    expect($junior->fresh()->manager_id)->toBeNull()
        ->and($head->fresh()->manager_id)->toBe($junior->id)
        ->and($middle->fresh()->manager_id)->toBe($head->id);

    // Walking up from every node terminates, so no cycle was created.
    foreach ([$head, $middle, $junior] as $employee) {
        $steps = 0;
        $cursor = $employee->fresh()->manager_id;

        while ($cursor !== null && $steps < 10) {
            $cursor = Employee::whereKey($cursor)->value('manager_id');
            $steps++;
        }

        expect($cursor)->toBeNull();
    }
});

it('refuses to make somebody their own manager', function (): void {
    $staff = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $staff), ['manager_id' => (string) $staff->id])
        ->assertSessionHas('error');

    expect($staff->fresh()->manager_id)->not->toBe($staff->id);
});

it('will not rearrange another tenant reporting line', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active']);
    $stranger = Employee::create([
        'tenant_id' => $other->id,
        'employee_number' => 'X-001',
        'full_name' => 'Orang Luar',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.organisasi.atasan', $stranger), ['manager_id' => 'none'])
        ->assertStatus(404);

    expect($stranger->fresh()->is_top_approver)->toBeFalse();
});

it('tells the chart whether the reader may rearrange it', function (): void {
    actingAs($this->admin)
        ->get(route('avana.organisasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/employees/org-chart', false)
            ->where('canManage', true)
            ->has('nodes'));
});

it('keeps the "Belum ditentukan" choice out of the director flag', function (): void {
    // The first hires of a company have nobody above them yet, but they are not
    // the board either. Before this sentinel existed the picker offered them
    // only "Tidak ada — Approver Puncak", so a fresh tenant could not record a
    // rank-and-file employee without granting them self-approval.
    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Sari Staf',
            'employment_status' => 'permanent',
            'status' => 'active',
            'manager_id' => 'unassigned',
        ])
        ->assertRedirect(route('avana.employees.index'));

    $employee = Employee::where('full_name', 'Sari Staf')->firstOrFail();

    expect($employee->is_top_approver)->toBeFalse()
        ->and($employee->manager_id)->toBeNull();
});

it('drops the director flag when a director is moved to "Belum ditentukan"', function (): void {
    $director = Employee::forTenant($this->tenant->id)->firstOrFail();
    $director->update(['manager_id' => null, 'is_top_approver' => true]);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $director), [
            'full_name' => $director->full_name,
            'employment_status' => $director->employment_status,
            'status' => 'active',
            'manager_id' => 'unassigned',
        ])
        ->assertRedirect();

    expect($director->fresh()->is_top_approver)->toBeFalse()
        ->and($director->fresh()->manager_id)->toBeNull();
});

it('clears the director flag once somebody is given a manager', function (): void {
    $director = Employee::forTenant($this->tenant->id)->firstOrFail();
    $director->update(['manager_id' => null, 'is_top_approver' => true]);

    $manager = Employee::forTenant($this->tenant->id)->whereKeyNot($director->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.employees.update', $director), [
            'full_name' => $director->full_name,
            'employment_status' => $director->employment_status,
            'status' => 'active',
            'manager_id' => (string) $manager->id,
        ])
        ->assertRedirect();

    expect($director->fresh()->is_top_approver)->toBeFalse()
        ->and($director->fresh()->manager_id)->toBe($manager->id);
});

it('does not flag a director as missing a manager on the chart', function (): void {
    // The chart tags a manager-less employee, but a director is meant to be
    // one — the flag is what tells the two apart.
    actingAs($this->admin)
        ->post(route('avana.employees.store'), [
            'full_name' => 'Direktur Chart',
            'employment_status' => 'permanent',
            'status' => 'active',
            'manager_id' => 'none',
        ])
        ->assertRedirect();

    actingAs($this->admin)
        ->get(route('avana.organisasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nodes', function ($nodes): bool {
                $row = collect($nodes)->firstWhere('name', 'Direktur Chart');

                return $row !== null
                    && $row['is_top_approver'] === true
                    && $row['manager_id'] === null;
            })
            ->etc());
});

it('lists only the employee own documents, leave and payroll history on the detail page', function (): void {
    $other = Employee::forTenant($this->tenant->id)->firstOrFail();
    $leaveType = LeaveType::forTenant($this->tenant->id)->firstOrFail();

    // A brand-new employee starts with no history, so every row the page shows
    // must be one this test created for them.
    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'employee_number' => 'EMP-DETAIL',
        'full_name' => 'Karyawan Detail',
        'employment_status' => 'permanent',
        'status' => 'active',
        'branch_id' => $other->branch_id,
    ]);

    EmployeeDocument::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'name' => 'Kontrak Kerja.pdf',
        'type' => 'kontrak',
        'file_path' => 'documents/kontrak.pdf',
        'file_size' => 1_258_291,
        'uploaded_at' => '2026-01-12',
    ]);
    EmployeeDocument::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $other->id,
        'name' => 'Milik Orang Lain.pdf',
        'file_path' => 'documents/lain.pdf',
    ]);

    LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-03-12',
        'end_date' => '2026-03-14',
        'total_days' => 3,
        'status' => 'approved',
    ]);
    LeaveRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $other->id,
        'branch_id' => $other->branch_id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-01',
        'total_days' => 1,
        'status' => 'pending',
    ]);

    $period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PR-2026-05-DETAIL',
        'name' => 'Mei 2026',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-31',
        'status' => 'locked',
    ]);
    $run = PayrollRun::create([
        'tenant_id' => $this->tenant->id,
        'payroll_period_id' => $period->id,
        'branch_id' => $employee->branch_id,
        'status' => 'locked',
    ]);
    PayrollRunItem::create([
        'tenant_id' => $this->tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'net_salary' => 11_323_000,
    ]);
    PayrollRunItem::create([
        'tenant_id' => $this->tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $other->id,
        'net_salary' => 9_000_000,
    ]);

    actingAs($this->admin)
        ->get(route('avana.employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/employees/show', false)
            ->has('employee.data.documents', 1)
            ->where('employee.data.documents.0.name', 'Kontrak Kerja.pdf')
            ->where('employee.data.documents.0.size_label', '1.2 MB')
            ->where('employee.data.documents.0.uploaded_at', '12 Jan 2026')
            ->has('employee.data.leave_history', 1)
            ->where('employee.data.leave_history.0.type', $leaveType->name)
            ->where('employee.data.leave_history.0.date_label', '12–14 Mar 2026')
            ->where('employee.data.leave_history.0.duration_label', '3 hari')
            ->where('employee.data.leave_history.0.status_label', 'Disetujui')
            ->has('employee.data.payroll_history', 1)
            ->where('employee.data.payroll_history.0.period', 'Mei 2026')
            ->where('employee.data.payroll_history.0.net_salary_label', 'Rp 11.323.000')
            ->where('employee.data.payroll_history.0.status_label', 'Terkunci'));
});

it('shows empty document, leave and payroll tabs for a freshly created employee', function (): void {
    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'employee_number' => 'EMP-BARU',
        'full_name' => 'Karyawan Baru',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->get(route('avana.employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('employee.data.documents', 0)
            ->has('employee.data.leave_history', 0)
            ->has('employee.data.payroll_history', 0)
            ->has('employee.data.held_assets', 0));
});

it('sends the birth date formatted for display and raw for the edit form', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $employee->update(['birth_date' => '1978-03-11']);

    actingAs($this->admin)
        ->get(route('avana.employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.data.birth_date', '11 Mar 1978')
            ->where('employee.data.birth_date_raw', '1978-03-11')
            ->etc());

    actingAs($this->admin)
        ->get(route('avana.employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.data.birth_date_raw', '1978-03-11')
            ->etc());
});
