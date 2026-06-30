<?php

use App\Models\Employee;
use App\Models\Loan;
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
 * Create a pending loan for the given tenant.
 */
function makeLoan(int $tenantId, array $overrides = []): Loan
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return Loan::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'amount' => 6_000_000,
        'tenor_months' => 6,
        'interest_rate' => 0,
        'monthly_installment' => 1_000_000,
        'purpose' => 'Renovasi rumah',
        'status' => 'pending',
    ], $overrides));
}

it('renders the paginated pinjaman index with the expected props', function (): void {
    makeLoan($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.pinjaman'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/pinjaman/index', false)
            ->has('loans.data')
            ->has('loans.meta.total')
            ->has('loans.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('amount')
                ->has('tenor_months')
                ->has('interest_rate')
                ->has('monthly_installment')
                ->has('purpose')
                ->has('status')
                ->has('status_label')
                ->has('approved_at')
                ->etc())
            ->has('filters')
            ->has('employees')
            ->has('kpis.outstanding_total')
            ->has('kpis.pending_count'));
});

it('only lists loans that belong to the current tenant', function (): void {
    makeLoan($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    Loan::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'amount' => 1_000_000,
        'tenor_months' => 2,
        'interest_rate' => 0,
        'monthly_installment' => 500_000,
        'status' => 'pending',
    ]);

    $tenantTotal = Loan::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.pinjaman'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('loans.meta.total', $tenantTotal));
});

it('creates a pending loan with a computed monthly installment', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.pinjaman.store'), [
            'employee_id' => $employee->id,
            'amount' => 1_200_000,
            'tenor_months' => 12,
            'interest_rate' => 10,
            'purpose' => 'Biaya pendidikan',
        ])
        ->assertRedirect(route('avana.pinjaman'))
        ->assertSessionHas('success');

    $loan = Loan::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($loan->tenant_id)->toBe($this->tenant->id);
    expect($loan->status)->toBe('pending');
    expect((float) $loan->amount)->toBe(1_200_000.0);
    // 1.200.000 * (1 + 10/100) / 12 = 110.000
    expect((float) $loan->monthly_installment)->toBe(110_000.0);
});

it('defaults the interest rate to zero when omitted', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.pinjaman.store'), [
            'employee_id' => $employee->id,
            'amount' => 3_000_000,
            'tenor_months' => 4,
        ])
        ->assertSessionHas('success');

    $loan = Loan::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect((float) $loan->interest_rate)->toBe(0.0);
    expect((float) $loan->monthly_installment)->toBe(750_000.0);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pinjaman.store'), [
            'employee_id' => '',
            'amount' => 0,
            'tenor_months' => 0,
        ])
        ->assertSessionHasErrors(['employee_id', 'amount', 'tenor_months']);
});

it('approves a loan and stamps the approval time', function (): void {
    $loan = makeLoan($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.pinjaman.approve', $loan))
        ->assertSessionHas('success');

    $loan->refresh();
    expect($loan->status)->toBe('approved');
    expect($loan->approved_at)->not->toBeNull();
});

it('rejects a loan', function (): void {
    $loan = makeLoan($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.pinjaman.reject', $loan))
        ->assertSessionHas('success');

    expect($loan->fresh()->status)->toBe('rejected');
});

it('deletes a loan', function (): void {
    $loan = makeLoan($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.pinjaman.destroy', $loan))
        ->assertSessionHas('success');

    expect(Loan::find($loan->id))->toBeNull();
});

it('returns 404 when approving a loan from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = Loan::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'amount' => 1_000_000,
        'tenor_months' => 1,
        'interest_rate' => 0,
        'monthly_installment' => 1_000_000,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.pinjaman.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids a plain employee from listing or creating loans', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.pinjaman'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.pinjaman.store'), [
            'employee_id' => Employee::forTenant($this->tenant->id)->value('id'),
            'amount' => 1_000_000,
            'tenor_months' => 1,
        ])
        ->assertForbidden();
});
