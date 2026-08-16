<?php

use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeContract;
use App\Models\SalaryMaster;
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
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    $this->master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-LINK-SPEC',
        'category' => 'Spec',
        'is_active' => true,
    ]);
});

/** The employee edit payload, with the fields under test overridable. */
function employeePayload(object $ctx, array $overrides = []): array
{
    $employee = $ctx->employee;

    return employeeFormPayload($employee->tenant_id, array_merge([
        'full_name' => $employee->full_name,
        'employee_number' => $employee->employee_number,
        'email' => $employee->email,
        'employment_status' => $employee->employment_status,
        'status' => $employee->status,
        'branch_id' => $employee->branch_id,
    ], $overrides));
}

it('shows which Master Gaji the employee is paid from', function (): void {
    $this->employee->update(['salary_master_id' => $this->master->id]);

    actingAs($this->admin)
        ->get(route('avana.employees.show', $this->employee))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $employee = $page->toArray()['props']['employee']['data'] ?? $page->toArray()['props']['employee'];

            expect($employee['salary_master']['code'])->toBe('MG-LINK-SPEC');
            expect($employee['salary_master_id'])->toBe($this->master->id);
        });
});

it('shows both BPJS membership numbers on the employee page', function (): void {
    EmployeeBpjsProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id],
        [
            'bpjs_kesehatan_number' => '0001234567890',
            'bpjs_ketenagakerjaan_number' => '21012345678',
        ],
    );

    actingAs($this->admin)
        ->get(route('avana.employees.show', $this->employee))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $employee = $page->toArray()['props']['employee']['data'] ?? $page->toArray()['props']['employee'];

            expect($employee['bpjs_kesehatan_number'])->toBe('0001234567890');
            expect($employee['bpjs_ketenagakerjaan_number'])->toBe('21012345678');
        });
});

it('saves the Master Gaji and the BPJS numbers from the employee form', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'salary_master_id' => $this->master->id,
            'bpjs_kesehatan_number' => '0009876543210',
            'bpjs_ketenagakerjaan_number' => '21098765432',
        ]))
        ->assertRedirect();

    $profile = EmployeeBpjsProfile::where('employee_id', $this->employee->id)->firstOrFail();

    expect($this->employee->fresh()->salary_master_id)->toBe($this->master->id);
    expect($profile->bpjs_kesehatan_number)->toBe('0009876543210');
    expect($profile->bpjs_ketenagakerjaan_number)->toBe('21098765432');
});

it('keeps the BPJS numbers off the employee row itself', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'bpjs_kesehatan_number' => '0001111111111',
        ]))
        ->assertRedirect();

    // The column lives on the profile; writing it to the employee would throw.
    expect($this->employee->fresh()->getAttributes())->not->toHaveKey('bpjs_kesehatan_number');
});

it('refuses a Master Gaji belonging to another tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-emp', 'status' => 'active']);
    $foreign = SalaryMaster::create([
        'tenant_id' => $other->id,
        'code' => 'MG-ASING',
        'category' => 'Asing',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'salary_master_id' => $foreign->id,
        ]))
        ->assertSessionHasErrors('salary_master_id');
});

it('offers the Master Gaji list on the employee form', function (): void {
    actingAs($this->admin)
        ->get(route('avana.employees.edit', $this->employee))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $names = collect($page->toArray()['props']['options']['salaryMasters'])->pluck('name');

            expect($names)->toContain('MG-LINK-SPEC · Spec');
        });
});

it('lists a contract typed on the employee form on the Kontrak screen', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'contract_number' => 'PKWT-2026-001',
            'contract_type' => 'PKWT',
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2026-12-31',
        ]))
        ->assertRedirect();

    $contract = EmployeeContract::forTenant($this->tenant->id)
        ->where('contract_number', 'PKWT-2026-001')
        ->firstOrFail();

    expect($contract->employee_id)->toBe($this->employee->id);
    expect($contract->status)->toBe('active');

    actingAs($this->admin)
        ->get(route('avana.kontrak'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $numbers = collect($page->toArray()['props']['contracts']['data'])->pluck('contract_number');

            expect($numbers)->toContain('PKWT-2026-001');
        });
});

it('corrects the same contract instead of stacking duplicates', function (): void {
    $payload = employeePayload($this, [
        'contract_number' => 'PKWT-2026-002',
        'contract_type' => 'PKWT',
        'contract_start_date' => '2026-01-01',
        'contract_end_date' => '2026-12-31',
    ]);

    actingAs($this->admin)->put(route('avana.employees.update', $this->employee), $payload)->assertRedirect();
    actingAs($this->admin)->put(route('avana.employees.update', $this->employee), array_merge($payload, [
        'contract_type' => 'PKWTT',
    ]))->assertRedirect();

    $contracts = EmployeeContract::forTenant($this->tenant->id)
        ->where('contract_number', 'PKWT-2026-002')
        ->get();

    expect($contracts)->toHaveCount(1);
    expect($contracts->first()->contract_type)->toBe('pkwtt');
});

it('refuses the form with no contract number and writes no contract', function (): void {
    $before = EmployeeContract::forTenant($this->tenant->id)->count();

    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, ['contract_number' => '']))
        ->assertSessionHasErrors('contract_number');

    expect(EmployeeContract::forTenant($this->tenant->id)->count())->toBe($before);
});

it('requires the contract kind and its dates', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'contract_number' => 'PKWT-2026-003',
            'contract_type' => '',
            'contract_start_date' => '',
            'contract_end_date' => '',
        ]))
        ->assertSessionHasErrors(['contract_type', 'contract_start_date']);
});

it('addresses an employee by an opaque key, not a countable id', function (): void {
    $employee = $this->employee->fresh();

    expect($employee->public_id)->toHaveLength(26);
    expect($employee->getRouteKey())->toBe($employee->public_id);
    expect(route('avana.employees.show', $employee))->toEndWith('/avana/employees/'.$employee->public_id);
});

it('no longer answers to the numeric id', function (): void {
    actingAs($this->admin)
        ->get('/avana/employees/'.$this->employee->id)
        ->assertNotFound();
});

it('gives every newly created employee its own key', function (): void {
    $created = Employee::create([
        'tenant_id' => $this->tenant->id,
        'employee_number' => 'EMP-KEY-1',
        'full_name' => 'Karyawan Baru',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    expect($created->public_id)->not->toBeNull();
    expect($created->public_id)->not->toBe($this->employee->public_id);
});

it('still refuses an employee from another tenant, key or no key', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-key', 'status' => 'active']);
    $foreign = Employee::create([
        'tenant_id' => $other->id,
        'employee_number' => 'EMP-ASING',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    // The opaque key is not the protection — the tenant check is, and it holds.
    // Not found rather than forbidden, so the answer does not confirm the
    // record exists somewhere else.
    actingAs($this->admin)
        ->get(route('avana.employees.show', $foreign))
        ->assertNotFound();
});

it('prefills the employee form with the contract already on file', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    EmployeeContract::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'contract_number' => 'PKWT-PREFILL-1',
        'contract_type' => 'pkwt',
        'start_date' => '2026-01-05',
        'end_date' => '2027-01-04',
        'basic_salary' => 8_000_000,
        'status' => 'active',
    ]);

    $props = actingAs($this->admin)
        ->get(route('avana.employees.edit', $employee))
        ->assertOk()
        ->viewData('page')['props'];

    $employeeProp = $props['employee'];
    $employeeProp = $employeeProp['data'] ?? $employeeProp;
    $contracts = collect($employeeProp['contracts'] ?? []);

    expect($contracts)->toHaveCount(1)
        ->and($contracts->first()['contract_number'])->toBe('PKWT-PREFILL-1')
        ->and($contracts->first()['contract_type'])->toBe('pkwt')
        ->and($contracts->first()['start_date_raw'])->toBe('2026-01-05')
        ->and($contracts->first()['end_date_raw'])->toBe('2027-01-04');
});

it('stores one spelling of a contract kind whatever was typed', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'contract_number' => 'PKWT-CASE-1',
            'contract_type' => 'PKWTT',
            'contract_start_date' => '2026-02-01',
            'contract_end_date' => '',
        ]))
        ->assertRedirect();

    $contract = EmployeeContract::forTenant($this->tenant->id)
        ->where('contract_number', 'PKWT-CASE-1')
        ->firstOrFail();

    // The Kontrak screen's dropdown only knows the lower-case keys; anything
    // else falls back to its first option and silently rewrites the kind.
    expect($contract->contract_type)->toBe('pkwtt');

    actingAs($this->admin)
        ->put(route('avana.kontrak.update', $contract), [
            'employee_id' => $this->employee->id,
            'contract_number' => 'PKWT-CASE-1',
            'contract_type' => 'PKWTT',
            'start_date' => '2026-02-01',
            'basic_salary' => 9_000_000,
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($contract->fresh()->contract_type)->toBe('pkwtt');
});

it('prefills the employee form with the BPJS numbers and PTKP already on file', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'bpjs_ketenagakerjaan_number' => '1122334455',
            'bpjs_kesehatan_number' => '0001112223334',
            'ptkp_status' => 'K/1',
        ]))
        ->assertRedirect();

    $props = actingAs($this->admin)
        ->get(route('avana.employees.edit', $this->employee))
        ->assertOk()
        ->viewData('page')['props'];

    $employee = $props['employee']['data'] ?? $props['employee'];

    // The resource drops a field whose relation was not eager loaded, so a
    // missing key here means the form renders blank over stored data.
    expect($employee['bpjs_ketenagakerjaan_number'] ?? null)->toBe('1122334455')
        ->and($employee['bpjs_kesehatan_number'] ?? null)->toBe('0001112223334')
        ->and($employee['ptkp_status'] ?? null)->toBe('K/1');
});

it('keeps the BPJS number a partial payload did not mention', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'bpjs_ketenagakerjaan_number' => '9988776655',
            'bpjs_kesehatan_number' => '0004445556667',
        ]))
        ->assertRedirect();

    // A payload carrying only one of the two used to null out the other.
    $payload = employeePayload($this, ['bpjs_ketenagakerjaan_number' => '9988776655']);
    unset($payload['bpjs_kesehatan_number']);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), $payload)
        ->assertRedirect();

    $profile = EmployeeBpjsProfile::where('employee_id', $this->employee->id)->firstOrFail();

    expect($profile->bpjs_ketenagakerjaan_number)->toBe('9988776655')
        ->and($profile->bpjs_kesehatan_number)->toBe('0004445556667');
});

it('clears a BPJS number the form deliberately emptied', function (): void {
    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'bpjs_ketenagakerjaan_number' => '5544332211',
            'bpjs_kesehatan_number' => '0009998887776',
        ]))
        ->assertRedirect();

    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), employeePayload($this, [
            'bpjs_ketenagakerjaan_number' => '5544332211',
            'bpjs_kesehatan_number' => '',
        ]))
        ->assertRedirect();

    $profile = EmployeeBpjsProfile::where('employee_id', $this->employee->id)->firstOrFail();

    expect($profile->bpjs_ketenagakerjaan_number)->toBe('5544332211')
        ->and($profile->bpjs_kesehatan_number)->toBeNull();
});

it('hands the edit form back every Kepegawaian field that was just saved', function (): void {
    $payload = employeePayload($this, [
        'salary_master_id' => $this->master->id,
        'contract_number' => 'PKWT-2026-777',
        'contract_type' => 'pkwt',
        'contract_start_date' => '2026-01-01',
        'contract_end_date' => '2026-12-31',
        'bpjs_kesehatan_number' => '0001234567890',
        'bpjs_ketenagakerjaan_number' => '21012345678',
        'ptkp_status' => 'K/1',
    ]);

    actingAs($this->admin)
        ->put(route('avana.employees.update', $this->employee), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    actingAs($this->admin)
        ->get(route('avana.employees.edit', $this->employee->fresh()))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $employee = $page->toArray()['props']['employee']['data'];
            $contract = collect($employee['contracts'])->firstWhere('contract_number', 'PKWT-2026-777');

            expect($employee['salary_master_id'])->toBe($this->master->id);
            expect($employee['bpjs_kesehatan_number'])->toBe('0001234567890');
            expect($employee['bpjs_ketenagakerjaan_number'])->toBe('21012345678');
            expect($employee['ptkp_status'])->toBe('K/1');
            expect($contract)->not->toBeNull();
            expect($contract['status'])->toBe('active');
            expect($contract['contract_type'])->toBe('pkwt');
            expect($contract['start_date_raw'])->toBe('2026-01-01');
            expect($contract['end_date_raw'])->toBe('2026-12-31');
        });
});

it('carries every off-row field on each single-employee page', function (string $route): void {
    // These fields are written by the employee form but stored on other tables,
    // so a page that forgets to eager-load one renders those boxes empty and
    // reads as "my entry was never saved". Guard both pages at once, so a new
    // one cannot quietly drop a relation.
    actingAs($this->admin)
        ->get(route($route, $this->employee))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $employee = $page->toArray()['props']['employee']['data'];

            expect($employee)->toHaveKeys([
                'bpjs_kesehatan_number',
                'bpjs_ketenagakerjaan_number',
                'ptkp_status',
                'contracts',
            ]);
        });
})->with(['avana.employees.edit', 'avana.employees.show']);
