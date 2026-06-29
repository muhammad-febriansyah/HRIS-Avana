<?php

use App\Models\CashAdvance;
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
 * Create a pending cash advance for the given tenant.
 */
function makeCashAdvance(int $tenantId, array $overrides = []): CashAdvance
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return CashAdvance::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'amount' => 1_500_000,
        'installments' => 3,
        'monthly_deduction' => 500_000,
        'request_date' => '2026-07-01',
        'reason' => 'Kebutuhan mendesak',
        'status' => 'pending',
    ], $overrides));
}

it('renders the paginated kasbon index with the expected props', function (): void {
    makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.kasbon'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kasbon', false)
            ->has('requests.data')
            ->has('requests.meta.total')
            ->has('requests.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('amount')
                ->has('installments')
                ->has('monthly_deduction')
                ->has('request_date')
                ->has('reason')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('employees'));
});

it('only lists cash advances that belong to the current tenant', function (): void {
    makeCashAdvance($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    CashAdvance::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'amount' => 1_000_000,
        'installments' => 2,
        'monthly_deduction' => 500_000,
        'request_date' => '2026-07-01',
        'status' => 'pending',
    ]);

    $tenantTotal = CashAdvance::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.kasbon'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', $tenantTotal));
});

it('creates a pending cash advance with a computed monthly deduction', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kasbon.store'), [
            'employee_id' => $employee->id,
            'amount' => 3_000_000,
            'installments' => 4,
            'request_date' => '2026-07-10',
            'reason' => 'Renovasi rumah',
        ])
        ->assertRedirect(route('avana.kasbon'))
        ->assertSessionHas('success');

    $advance = CashAdvance::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($advance->tenant_id)->toBe($this->tenant->id);
    expect($advance->status)->toBe('pending');
    expect((float) $advance->amount)->toBe(3_000_000.0);
    expect((float) $advance->monthly_deduction)->toBe(750_000.0);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kasbon.store'), [
            'employee_id' => '',
            'amount' => 0,
            'installments' => 0,
            'request_date' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'amount', 'installments', 'request_date']);
});

it('approves a cash advance and records the approver', function (): void {
    $advance = makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.approve', $advance))
        ->assertSessionHas('success');

    $advance->refresh();
    expect($advance->status)->toBe('approved');
    expect((int) $advance->approved_by)->toBe($this->admin->id);
});

it('rejects a cash advance', function (): void {
    $advance = makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.reject', $advance))
        ->assertSessionHas('success');

    expect($advance->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving a cash advance from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = CashAdvance::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'amount' => 1_000_000,
        'installments' => 1,
        'monthly_deduction' => 1_000_000,
        'request_date' => '2026-07-01',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.kasbon.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids a plain employee from listing or creating cash advances', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.kasbon'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.kasbon.store'), [
            'employee_id' => Employee::forTenant($this->tenant->id)->value('id'),
            'amount' => 1_000_000,
            'installments' => 1,
            'request_date' => '2026-07-10',
        ])
        ->assertForbidden();
});
