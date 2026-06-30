<?php

use App\Models\Benefit;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
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
 * Create a benefit definition for the given tenant.
 */
function makeBenefit(int $tenantId, array $overrides = []): Benefit
{
    return Benefit::create(array_merge([
        'tenant_id' => $tenantId,
        'code' => 'BNF-'.fake()->unique()->numberBetween(1000, 9999),
        'name' => 'Tunjangan Transportasi',
        'type' => 'allowance',
        'value' => 500_000,
        'description' => 'Tunjangan harian',
        'status' => 'active',
    ], $overrides));
}

/**
 * Assign a benefit to an employee under the given tenant.
 */
function makeEmployeeBenefit(int $tenantId, array $overrides = []): EmployeeBenefit
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $benefit = makeBenefit($tenantId);

    return EmployeeBenefit::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'benefit_id' => $benefit->id,
        'start_date' => '2026-07-01',
        'end_date' => null,
        'status' => 'active',
        'notes' => null,
    ], $overrides));
}

it('renders the benefit index with the expected props', function (): void {
    makeBenefit($this->tenant->id);
    makeEmployeeBenefit($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.benefit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/benefit/index', false)
            ->has('benefits.0', fn (Assert $row) => $row
                ->has('id')
                ->has('code')
                ->has('name')
                ->has('type')
                ->has('value')
                ->has('description')
                ->has('status'))
            ->has('assignments.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.employee_number')
                ->has('benefit.name')
                ->has('benefit.type')
                ->has('start_date')
                ->has('end_date')
                ->has('status'))
            ->has('employees'));
});

it('only lists benefits and assignments that belong to the current tenant', function (): void {
    makeBenefit($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeBenefit($otherTenant->id);

    $tenantTotal = Benefit::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.benefit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('benefits', $tenantTotal));
});

it('creates a benefit scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.benefit.store'), [
            'code' => 'BNF-100',
            'name' => 'Asuransi Kesehatan',
            'type' => 'insurance',
            'value' => 1_000_000,
            'description' => 'Premi bulanan',
            'status' => 'active',
        ])
        ->assertRedirect(route('avana.benefit'))
        ->assertSessionHas('success');

    $benefit = Benefit::where('code', 'BNF-100')->firstOrFail();

    expect($benefit->tenant_id)->toBe($this->tenant->id);
    expect($benefit->type)->toBe('insurance');
    expect((float) $benefit->value)->toBe(1_000_000.0);
});

it('validates required fields and duplicate code on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.benefit.store'), [
            'code' => '',
            'name' => '',
            'type' => 'invalid',
            'value' => -5,
        ])
        ->assertSessionHasErrors(['code', 'name', 'type', 'value', 'status']);

    makeBenefit($this->tenant->id, ['code' => 'BNF-DUP']);

    actingAs($this->admin)
        ->post(route('avana.benefit.store'), [
            'code' => 'BNF-DUP',
            'name' => 'Duplikat',
            'type' => 'allowance',
            'value' => 100,
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['code']);
});

it('updates an existing benefit', function (): void {
    $benefit = makeBenefit($this->tenant->id, ['code' => 'BNF-OLD']);

    actingAs($this->admin)
        ->put(route('avana.benefit.update', $benefit), [
            'code' => 'BNF-NEW',
            'name' => 'Tunjangan Makan',
            'type' => 'facility',
            'value' => 750_000,
            'description' => null,
            'status' => 'inactive',
        ])
        ->assertRedirect(route('avana.benefit'))
        ->assertSessionHas('success');

    $benefit->refresh();

    expect($benefit->code)->toBe('BNF-NEW');
    expect($benefit->name)->toBe('Tunjangan Makan');
    expect($benefit->type)->toBe('facility');
    expect($benefit->status)->toBe('inactive');
});

it('deletes a benefit', function (): void {
    $benefit = makeBenefit($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.benefit.destroy', $benefit))
        ->assertSessionHas('success');

    expect(Benefit::find($benefit->id))->toBeNull();
});

it('assigns a benefit to an employee', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $benefit = makeBenefit($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.benefit.assign'), [
            'employee_id' => $employee->id,
            'benefit_id' => $benefit->id,
            'start_date' => '2026-08-01',
            'notes' => 'Mulai periode baru',
        ])
        ->assertRedirect(route('avana.benefit'))
        ->assertSessionHas('success');

    $assignment = EmployeeBenefit::where('employee_id', $employee->id)
        ->where('benefit_id', $benefit->id)
        ->firstOrFail();

    expect($assignment->tenant_id)->toBe($this->tenant->id);
    expect($assignment->status)->toBe('active');
});

it('validates the assign request against tenant-scoped relations', function (): void {
    actingAs($this->admin)
        ->post(route('avana.benefit.assign'), [
            'employee_id' => '',
            'benefit_id' => 99999,
        ])
        ->assertSessionHasErrors(['employee_id', 'benefit_id']);
});

it('unassigns a benefit from an employee', function (): void {
    $assignment = makeEmployeeBenefit($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.benefit.unassign', $assignment))
        ->assertSessionHas('success');

    expect(EmployeeBenefit::find($assignment->id))->toBeNull();
});

it('returns 404 when updating a benefit from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeBenefit($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.benefit.update', $foreign), [
            'code' => 'BNF-HACK',
            'name' => 'Hack',
            'type' => 'allowance',
            'value' => 1,
            'status' => 'active',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a benefit from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeBenefit($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.benefit.destroy', $foreign))
        ->assertNotFound();

    expect(Benefit::find($foreign->id))->not->toBeNull();
});

it('returns 404 when unassigning a benefit from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreignBenefit = makeBenefit($otherTenant->id);
    $foreign = EmployeeBenefit::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'benefit_id' => $foreignBenefit->id,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.benefit.unassign', $foreign))
        ->assertNotFound();

    expect(EmployeeBenefit::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from listing or creating benefits', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.benefit'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.benefit.store'), [
            'code' => 'BNF-NO',
            'name' => 'Tidak Boleh',
            'type' => 'allowance',
            'value' => 100,
            'status' => 'active',
        ])
        ->assertForbidden();
});
