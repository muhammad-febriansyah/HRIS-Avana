<?php

use App\Models\BpjsProgram;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeBpjsProgram;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->hr = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->hr->tenant_id);

    $this->staff = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('user_id', User::where('email', 'bagus.p@nusantara.co.id')->value('id'))
        ->firstOrFail();

    /** Give the karyawan role one extra module, as a tenant admin would. */
    $this->grantModule = function (string $module): void {
        $role = Role::where('code', 'employee')->firstOrFail();
        $permission = Permission::where('module', $module)->firstOrFail();

        $role->permissions()->syncWithoutDetaching([$permission->id]);
    };

    /** The employee payload the form posts, with overrides merged on top. */
    $this->payload = function (array $overrides = []) {
        return employeeFormPayload($this->tenant->id, array_merge([
            'full_name' => $this->staff->full_name,
            'email' => $this->staff->email,
            'employment_status' => $this->staff->employment_status,
            'status' => $this->staff->status,
            'branch_id' => $this->staff->branch_id,
            'join_date' => $this->staff->join_date?->format('Y-m-d'),
        ], $overrides));
    };
});

it('gives a plain karyawan their own dashboard', function (): void {
    actingAs($this->staff->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('avana/saya/dashboard'));
});

it('keeps the karyawan dashboard when the role gains a self-service module', function (): void {
    // Leave, so they can file their own — this alone used to hand them the
    // HR dashboard, because the old test demanded they hold nothing but
    // `own` and `ai`.
    ($this->grantModule)('leave');

    actingAs($this->staff->user->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('avana/saya/dashboard'));
});

it('hands the HR dashboard to someone who can see the whole company', function (): void {
    actingAs($this->hr)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('moves a karyawan to the HR dashboard once they are given the employee directory', function (): void {
    ($this->grantModule)('employee');

    actingAs($this->staff->user->fresh())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));
});

it('does not strip an account of its other roles when the employee is edited', function (): void {
    $user = $this->staff->user;
    $manager = Role::where('code', 'manager')->firstOrFail();
    $employeeRole = Role::where('code', 'employee')->firstOrFail();

    $user->roles()->sync([$manager->id, $employeeRole->id]);

    // The form shows one role. Saving with it must not sync the account down
    // to that one and drop the other.
    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'role_id' => $manager->id,
        ]))
        ->assertRedirect();

    expect($user->fresh()->roles->pluck('code')->sort()->values()->all())
        ->toBe(['employee', 'manager']);
});

it('still applies a role the account does not already hold', function (): void {
    $user = $this->staff->user;
    $employeeRole = Role::where('code', 'employee')->firstOrFail();
    $finance = Role::where('code', 'finance')->firstOrFail();

    $user->roles()->sync([$employeeRole->id]);

    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'role_id' => $finance->id,
        ]))
        ->assertRedirect();

    expect($user->fresh()->roles->pluck('code')->all())->toBe(['finance']);
});

it('saves the PTKP status from the employee form', function (): void {
    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'ptkp_status' => 'K/2',
        ]))
        ->assertRedirect();

    expect(TaxProfile::where('employee_id', $this->staff->id)->value('ptkp_status'))->toBe('K/2');
});

it('saves employee BPJS participation against tenant programs', function (): void {
    $programs = BpjsProgram::forTenant($this->tenant->id)
        ->whereIn('code', ['KESEHATAN', 'JHT'])
        ->pluck('id', 'code');

    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'bpjs_participation_status' => 'participant',
            'bpjs_program_ids' => [$programs['KESEHATAN'], $programs['JHT']],
            'bpjs_registered_wage' => 5_000_000,
        ]))
        ->assertRedirect();

    $profile = EmployeeBpjsProfile::where('employee_id', $this->staff->id)->firstOrFail();

    expect($profile->participation_status)->toBe('participant')
        ->and((float) $profile->registered_wage)->toBe(5_000_000.0)
        ->and(EmployeeBpjsProgram::where('employee_id', $this->staff->id)
            ->pluck('program_id')
            ->sort()
            ->values()
            ->all())
        ->toBe($programs->values()->sort()->values()->all());
});

it('removes employee BPJS enrollments when participation is disabled', function (): void {
    $program = BpjsProgram::forTenant($this->tenant->id)->where('code', 'JHT')->firstOrFail();

    EmployeeBpjsProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id],
        ['participation_status' => 'participant'],
    );
    EmployeeBpjsProgram::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->staff->id,
        'program_id' => $program->id,
    ]);

    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'bpjs_participation_status' => 'not_participant',
            'bpjs_program_ids' => [],
        ]))
        ->assertRedirect();

    expect(EmployeeBpjsProfile::where('employee_id', $this->staff->id)->value('participation_status'))
        ->toBe('not_participant')
        ->and(EmployeeBpjsProgram::where('employee_id', $this->staff->id)->exists())
        ->toBeFalse();
});

it('rejects a BPJS program owned by another tenant', function (): void {
    $foreignTenant = Tenant::create([
        'name' => 'Tenant BPJS Lain',
        'slug' => 'tenant-bpjs-lain',
    ]);
    $foreignProgram = BpjsProgram::create([
        'tenant_id' => $foreignTenant->id,
        'code' => 'JHT',
        'name' => 'JHT Tenant Lain',
        'type' => 'jht',
        'is_active' => true,
    ]);

    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'bpjs_participation_status' => 'participant',
            'bpjs_program_ids' => [$foreignProgram->id],
        ]))
        ->assertSessionHasErrors('bpjs_program_ids.0');

    expect(EmployeeBpjsProgram::where('employee_id', $this->staff->id)->exists())->toBeFalse();
});

it('refuses a PTKP code the calculator does not know', function (): void {
    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'ptkp_status' => 'K/9',
        ]))
        ->assertSessionHasErrors('ptkp_status');
});

it('shows the PTKP status on the employee page', function (): void {
    TaxProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id],
        ['ptkp_status' => 'TK/1'],
    );

    actingAs($this->hr)
        ->get(route('avana.employees.show', $this->staff))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('employee.data.ptkp_status', 'TK/1'));
});

it('refuses a form with no PTKP and leaves the tax profile alone', function (): void {
    TaxProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id],
        ['ptkp_status' => 'K/3'],
    );

    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)(['ptkp_status' => '']))
        ->assertSessionHasErrors('ptkp_status');

    expect(TaxProfile::where('employee_id', $this->staff->id)->value('ptkp_status'))->toBe('K/3');
});

it('leaves the account untouched when only the employee data is edited', function (): void {
    $user = $this->staff->user;
    $manager = Role::where('code', 'manager')->firstOrFail();
    $employeeRole = Role::where('code', 'employee')->firstOrFail();

    $user->roles()->sync([$manager->id, $employeeRole->id]);
    $user->update(['status' => 'inactive']);

    $before = $user->fresh();

    // What the edit form posts when someone corrects a phone number: the role
    // it happens to display rides along, and the password box is left empty.
    actingAs($this->hr)
        ->put(route('avana.employees.update', $this->staff), ($this->payload)([
            'phone' => '081200009999',
            'role_id' => $manager->id,
            'password' => '',
        ]))
        ->assertRedirect();

    $after = $user->fresh();

    expect($this->staff->fresh()->phone)->toBe('081200009999')
        // Nothing in Akun & Akses was asked to change, so nothing did.
        ->and($after->password)->toBe($before->password)
        ->and($after->status)->toBe('inactive')
        ->and($after->roles->pluck('code')->sort()->values()->all())->toBe(['employee', 'manager']);
});

it('does not create a login for an employee who has none', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->whereNull('user_id')->first();

    if ($employee === null) {
        $employee = Employee::forTenant($this->tenant->id)->firstOrFail()->replicate();
        $employee->forceFill([
            'user_id' => null,
            'employee_number' => 'EMP-NOLOGIN',
            'email' => 'nologin@nusantara.co.id',
            'public_id' => (string) Str::ulid(),
        ])->save();
    }

    $roleId = Role::where('code', 'employee')->value('id');
    $usersBefore = User::count();

    // An empty password box means "no account", not "make one".
    actingAs($this->hr)
        ->put(route('avana.employees.update', $employee), array_merge(($this->payload)(), [
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'role_id' => $roleId,
            'password' => '',
        ]))
        ->assertRedirect();

    expect($employee->fresh()->user_id)->toBeNull()
        ->and(User::count())->toBe($usersBefore);
});
