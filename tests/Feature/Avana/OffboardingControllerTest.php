<?php

use App\Models\ClearanceItem;
use App\Models\Employee;
use App\Models\OffboardingCase;
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
 * Create an employee under the given tenant.
 */
function makeOffboardingEmployee(int $tenantId, array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
        'full_name' => fake()->name(),
    ], $overrides));
}

/**
 * Create an offboarding case (with its seeded checklist) for the given tenant.
 */
function makeOffboardingCase(int $tenantId, array $overrides = []): OffboardingCase
{
    $employeeId = $overrides['employee_id'] ?? makeOffboardingEmployee($tenantId)->id;

    $case = OffboardingCase::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'last_day' => '2026-08-31',
        'reason' => 'Resign',
        'status' => 'in_progress',
    ], $overrides));

    foreach (['IT', 'HR', 'Finance', 'Asset'] as $department) {
        $case->clearanceItems()->create([
            'tenant_id' => $tenantId,
            'title' => "Clearance {$department}",
            'department' => $department,
            'is_cleared' => false,
        ]);
    }

    return $case;
}

it('renders the offboarding index with the expected props', function (): void {
    makeOffboardingCase($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.offboarding'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/offboarding/index', false)
            ->has('cases.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee_id')
                ->has('employee')
                ->has('last_day')
                ->has('reason')
                ->has('status')
                ->has('items')
                ->has('items_total')
                ->has('items_cleared'))
            ->has('employees')
            ->has('kpis'));
});

it('only lists cases that belong to the current tenant', function (): void {
    makeOffboardingCase($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeOffboardingCase($otherTenant->id);

    $tenantTotal = OffboardingCase::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.offboarding'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('cases', $tenantTotal));
});

it('creates a case and seeds the default clearance checklist', function (): void {
    $employee = makeOffboardingEmployee($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.offboarding.store'), [
            'employee_id' => $employee->id,
            'last_day' => '2026-09-30',
            'reason' => 'Pindah kerja',
        ])
        ->assertRedirect(route('avana.offboarding'))
        ->assertSessionHas('success');

    $case = OffboardingCase::where('employee_id', $employee->id)->firstOrFail();

    expect($case->tenant_id)->toBe($this->tenant->id);
    expect($case->status)->toBe('in_progress');
    expect($case->clearanceItems()->count())->toBe(4);
    expect($case->clearanceItems()->pluck('department')->all())
        ->toEqual(['IT', 'HR', 'Finance', 'Asset']);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.offboarding.store'), [
            'employee_id' => 99999,
        ])
        ->assertSessionHasErrors(['employee_id']);
});

it('toggles a clearance item and recomputes the case status', function (): void {
    $case = makeOffboardingCase($this->tenant->id);

    foreach ($case->clearanceItems as $item) {
        actingAs($this->admin)
            ->post(route('avana.offboarding.item.toggle', $item))
            ->assertSessionHas('success');
    }

    $case->refresh();
    expect($case->status)->toBe('completed');

    $last = $case->clearanceItems()->first();
    actingAs($this->admin)
        ->post(route('avana.offboarding.item.toggle', $last))
        ->assertSessionHas('success');

    $case->refresh();
    expect($case->status)->toBe('in_progress');
});

it('deletes a case with its clearance items', function (): void {
    $case = makeOffboardingCase($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.offboarding.destroy', $case))
        ->assertSessionHas('success');

    expect(OffboardingCase::find($case->id))->toBeNull();
    expect(ClearanceItem::where('offboarding_case_id', $case->id)->count())->toBe(0);
});

it('returns 404 when toggling a clearance item from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeOffboardingCase($otherTenant->id);
    $item = $foreign->clearanceItems()->first();

    actingAs($this->admin)
        ->post(route('avana.offboarding.item.toggle', $item))
        ->assertNotFound();
});

it('returns 404 when deleting a case from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeOffboardingCase($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.offboarding.destroy', $foreign))
        ->assertNotFound();

    expect(OffboardingCase::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from managing offboarding', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.offboarding'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.offboarding.store'), [
            'employee_id' => makeOffboardingEmployee($this->tenant->id)->id,
        ])
        ->assertForbidden();
});
