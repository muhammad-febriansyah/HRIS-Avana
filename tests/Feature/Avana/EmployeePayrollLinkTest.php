<?php

use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
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

    return array_merge([
        'full_name' => $employee->full_name,
        'employee_number' => $employee->employee_number,
        'email' => $employee->email,
        'employment_status' => $employee->employment_status,
        'status' => $employee->status,
        'branch_id' => $employee->branch_id,
    ], $overrides);
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
