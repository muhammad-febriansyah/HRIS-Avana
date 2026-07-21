<?php

use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
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
        'purpose' => 'Uang muka perjalanan dinas',
        'request_date' => '2026-07-01',
        'reason' => 'Kebutuhan operasional lapangan',
        'status' => 'pending',
    ], $overrides));
}

it('renders the paginated cash advance index with the expected props', function (): void {
    makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.kasbon'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kasbon/index', false)
            ->has('requests.data')
            ->has('requests.meta.total')
            ->has('requests.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('amount')
                ->has('purpose')
                ->has('request_date')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('employees')
            ->has('disbursementMethods')
            ->has('kpis.pending'));
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
        'purpose' => 'Uang muka lain',
        'request_date' => '2026-07-01',
        'status' => 'pending',
    ]);

    $tenantTotal = CashAdvance::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.kasbon'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', $tenantTotal));
});

it('creates a pending cash advance', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kasbon.store'), [
            'employee_id' => $employee->id,
            'amount' => 3_000_000,
            'purpose' => 'Uang muka pembelian operasional',
            'request_date' => '2026-07-10',
            'needed_date' => '2026-07-15',
            'reason' => 'Pembelian ATK cabang',
        ])
        ->assertRedirect(route('avana.kasbon'))
        ->assertSessionHas('success');

    $advance = CashAdvance::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($advance->tenant_id)->toBe($this->tenant->id);
    expect($advance->status)->toBe('pending');
    expect((float) $advance->amount)->toBe(3_000_000.0);
    expect($advance->purpose)->toBe('Uang muka pembelian operasional');
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.kasbon.store'), [
            'employee_id' => '',
            'amount' => 0,
            'purpose' => '',
            'request_date' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'amount', 'purpose', 'request_date']);
});

it('rejects a needed date that falls before the request date', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.kasbon.store'), [
            'employee_id' => $employee->id,
            'amount' => 1_000_000,
            'purpose' => 'Uang muka',
            'request_date' => '2026-07-10',
            'needed_date' => '2026-07-01',
        ])
        ->assertSessionHasErrors(['needed_date']);
});

it('approves a cash advance and records the approver', function (): void {
    $advance = makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.approve', $advance))
        ->assertSessionHas('success');

    $advance->refresh();
    expect($advance->status)->toBe('approved');
    expect((int) $advance->approved_by)->toBe($this->admin->id);
    expect($advance->approved_at)->not->toBeNull();
});

it('rejects a cash advance', function (): void {
    $advance = makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.reject', $advance))
        ->assertSessionHas('success');

    expect($advance->fresh()->status)->toBe('rejected');
});

it('disburses an approved cash advance and records how the money moved', function (): void {
    $advance = makeCashAdvance($this->tenant->id, ['status' => 'approved']);

    actingAs($this->admin)
        ->post(route('avana.kasbon.disburse', $advance), [
            'disbursement_method' => 'transfer',
            'disbursement_reference' => 'TRF-99881',
        ])
        ->assertSessionHas('success');

    $advance->refresh();
    expect($advance->status)->toBe('disbursed');
    expect($advance->disbursement_method)->toBe('transfer');
    expect($advance->disbursement_reference)->toBe('TRF-99881');
    expect((int) $advance->disbursed_by)->toBe($this->admin->id);
    expect($advance->disbursed_at)->not->toBeNull();
});

it('refuses to disburse a cash advance that was never approved', function (): void {
    $advance = makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.disburse', $advance), [
            'disbursement_method' => 'transfer',
        ])
        ->assertStatus(422);

    expect($advance->fresh()->status)->toBe('pending');
});

it('refuses to approve a cash advance twice', function (): void {
    $advance = makeCashAdvance($this->tenant->id, ['status' => 'approved']);

    actingAs($this->admin)
        ->post(route('avana.kasbon.approve', $advance))
        ->assertStatus(422);
});

it('refuses to edit a cash advance whose money is already out the door', function (): void {
    $advance = makeCashAdvance($this->tenant->id, ['status' => 'disbursed']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.kasbon.update', $advance), [
            'employee_id' => $employee->id,
            'amount' => 9_000_000,
            'purpose' => 'Diubah diam-diam',
            'request_date' => '2026-07-01',
        ])
        ->assertStatus(422);

    expect((float) $advance->fresh()->amount)->toBe(1_500_000.0);
});

it('updates a cash advance that has not been disbursed', function (): void {
    $advance = makeCashAdvance($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.kasbon.update', $advance), [
            'employee_id' => $employee->id,
            'amount' => 2_250_000,
            'purpose' => 'Uang muka direvisi',
            'request_date' => '2026-07-01',
        ])
        ->assertRedirect(route('avana.kasbon'))
        ->assertSessionHas('success');

    $advance->refresh();
    expect((float) $advance->amount)->toBe(2_250_000.0);
    expect($advance->purpose)->toBe('Uang muka direvisi');
});

it('deletes a pending cash advance', function (): void {
    $advance = makeCashAdvance($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.kasbon.destroy', $advance))
        ->assertSessionHas('success');

    expect(CashAdvance::find($advance->id))->toBeNull();
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
        'purpose' => 'Uang muka asing',
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
            'purpose' => 'Uang muka',
            'request_date' => '2026-07-10',
        ])
        ->assertForbidden();
});

/**
 * A disbursed advance, released by someone other than the acting admin so the
 * four-eyes rule does not get in the way of what the test is actually checking.
 */
function disbursedAdvance(int $tenantId, float $amount = 2_000_000): CashAdvance
{
    $releaser = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    return makeCashAdvance($tenantId, [
        'amount' => $amount,
        'status' => 'disbursed',
        'approved_by' => $releaser->id,
        'approved_at' => now()->subDays(2),
        'disbursed_at' => now()->subDay(),
        'disbursed_by' => $releaser->id,
        'disbursement_method' => 'transfer',
    ]);
}

it('settles an advance the employee underspent, leaving money to return', function (): void {
    $advance = disbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), [
            'spent_amount' => 1_400_000,
            'settlement_note' => 'Hotel lebih murah dari perkiraan',
        ])
        ->assertSessionHas('success');

    $advance->refresh();

    expect($advance->status)->toBe('settled')
        ->and((float) $advance->spent_amount)->toBe(1_400_000.0)
        ->and((float) $advance->returned_amount)->toBe(600_000.0)
        ->and((float) $advance->topup_amount)->toBe(0.0)
        ->and($advance->settled_by)->toBe($this->admin->id)
        ->and($advance->settled_at)->not->toBeNull();
});

it('settles an advance the employee overspent, leaving a shortfall to pay', function (): void {
    $advance = disbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), ['spent_amount' => 2_350_000])
        ->assertSessionHas('success');

    $advance->refresh();

    expect((float) $advance->returned_amount)->toBe(0.0)
        ->and((float) $advance->topup_amount)->toBe(350_000.0);
});

it('leaves nothing owed either way when the advance was spent exactly', function (): void {
    $advance = disbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), ['spent_amount' => 2_000_000])
        ->assertSessionHas('success');

    $advance->refresh();

    expect((float) $advance->returned_amount)->toBe(0.0)
        ->and((float) $advance->topup_amount)->toBe(0.0);
});

it('stores the receipt proving what was spent', function (): void {
    Storage::fake('public');

    $advance = disbursedAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), [
            'spent_amount' => 1_000_000,
            'receipt' => UploadedFile::fake()->image('kuitansi.jpg'),
        ])
        ->assertSessionHas('success');

    $advance->refresh();

    expect($advance->settlement_receipt_path)->not->toBeNull();
    Storage::disk('public')->assertExists($advance->settlement_receipt_path);
});

it('blocks whoever released the money from signing off on it', function (): void {
    $advance = makeCashAdvance($this->tenant->id, [
        'status' => 'disbursed',
        'disbursed_at' => now(),
        'disbursed_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), ['spent_amount' => 1_000_000])
        ->assertForbidden();

    expect($advance->refresh()->status)->toBe('disbursed');
});

it('refuses to settle an advance that was never disbursed', function (): void {
    $advance = makeCashAdvance($this->tenant->id, ['status' => 'approved']);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), ['spent_amount' => 500_000])
        ->assertStatus(422);

    expect($advance->refresh()->status)->toBe('approved');
});

it('requires the amount actually spent', function (): void {
    $advance = disbursedAdvance($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), [])
        ->assertSessionHasErrors('spent_amount');
});

it('renders the detail with its approval trail', function (): void {
    $advance = disbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->get(route('avana.kasbon.show', $advance))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/kasbon/show', false)
            ->has('advance', fn (Assert $row) => $row
                ->where('status', 'disbursed')
                ->where('disbursement_method_label', 'Transfer Bank')
                ->has('approved_at')
                ->has('approved_by_name')
                ->has('disbursed_by_name')
                ->has('settled_at_full')
                ->etc())
            ->has('disbursementMethods')
            ->where('authUserId', $this->admin->id));
});

it('shows what a settled advance was spent on', function (): void {
    $advance = disbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->post(route('avana.kasbon.settle', $advance), [
            'spent_amount' => 1_400_000,
            'settlement_note' => 'Hotel lebih murah',
        ]);

    actingAs($this->admin)
        ->get(route('avana.kasbon.show', $advance))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('advance', fn (Assert $row) => $row
                ->where('status', 'settled')
                ->where('spent_amount', 1_400_000)
                ->where('returned_amount', 600_000)
                ->where('topup_amount', 0)
                ->where('settlement_note', 'Hotel lebih murah')
                ->where('settled_by_name', $this->admin->name)
                ->etc()));
});

it('hides an advance from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing Kasbon', 'slug' => 'pt-asing-kasbon']);
    $stranger = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-KB-1',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $foreign = makeCashAdvance($otherTenant->id, ['employee_id' => $stranger->id]);

    actingAs($this->admin)
        ->get(route('avana.kasbon.show', $foreign))
        ->assertNotFound();
});

it('enforces action-level payroll permissions on cash advances', function (): void {
    $role = Role::create(['tenant_id' => $this->tenant->id, 'code' => 'ksb-viewer', 'name' => 'Kasbon Viewer', 'is_system' => false]);
    $role->permissions()->syncWithoutDetaching(Permission::where('code', 'payroll.view')->pluck('id'));
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->roles()->sync([$role->id]);

    $adv = makeCashAdvance($this->tenant->id);

    // view-only: can list, cannot create or approve.
    actingAs($user)->get(route('avana.kasbon'))->assertOk();
    actingAs($user)->get(route('avana.kasbon.create'))->assertForbidden();
    actingAs($user)->post(route('avana.kasbon.approve', $adv))->assertForbidden();

    // grant create: create form now allowed.
    $role->permissions()->syncWithoutDetaching(Permission::where('code', 'payroll.create')->pluck('id'));

    actingAs($user->fresh())->get(route('avana.kasbon.create'))->assertOk();
});
