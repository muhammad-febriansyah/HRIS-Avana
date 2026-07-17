<?php

use App\Models\Employee;
use App\Models\Reimbursement;
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
 * Create a pending reimbursement for the given tenant.
 */
function makeReimbursement(int $tenantId, array $overrides = []): Reimbursement
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return Reimbursement::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'number' => 'RMB-TEST-0001',
        'category' => 'transportasi',
        'title' => 'Taksi ke kantor klien',
        'amount' => 250_000,
        'expense_date' => '2026-07-05',
        'status' => 'pending',
    ], $overrides));
}

it('renders the reimbursement index with the expected props', function (): void {
    makeReimbursement($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.reimbursement'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/reimbursement/index', false)
            ->has('requests.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('number')
                ->has('employee.name')
                ->has('category')
                ->has('category_label')
                ->has('title')
                ->has('amount')
                ->has('expense_date')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('employees')
            ->has('categories')
            ->has('paymentMethods')
            ->has('kpis.pending'));
});

it('only lists reimbursements that belong to the current tenant', function (): void {
    makeReimbursement($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-rmb']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-8888',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    Reimbursement::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'number' => 'RMB-FOREIGN-0001',
        'category' => 'medical',
        'title' => 'Obat',
        'amount' => 100_000,
        'expense_date' => '2026-07-05',
        'status' => 'pending',
    ]);

    $tenantTotal = Reimbursement::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.reimbursement'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', $tenantTotal));
});

it('creates a pending reimbursement with a receipt and an allocated number', function (): void {
    Storage::fake('public');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.reimbursement.store'), [
            'employee_id' => $employee->id,
            'category' => 'komunikasi',
            'title' => 'Pulsa dan paket data Juli',
            'amount' => 300_000,
            'expense_date' => '2026-07-05',
            'description' => 'Paket data untuk kerja lapangan',
            'receipt' => UploadedFile::fake()->image('struk.jpg'),
        ])
        ->assertRedirect(route('avana.reimbursement'))
        ->assertSessionHas('success');

    $reimbursement = Reimbursement::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($reimbursement->status)->toBe('pending');
    expect($reimbursement->category)->toBe('komunikasi');
    expect((float) $reimbursement->amount)->toBe(300_000.0);
    expect($reimbursement->number)->toStartWith('RMB-');
    Storage::disk('public')->assertExists($reimbursement->receipt_path);
});

it('allocates sequential numbers per tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    $payload = [
        'employee_id' => $employee->id,
        'category' => 'operasional',
        'title' => 'Pembelian ATK',
        'amount' => 150_000,
        'expense_date' => '2026-07-05',
    ];

    actingAs($this->admin)->post(route('avana.reimbursement.store'), $payload);
    actingAs($this->admin)->post(route('avana.reimbursement.store'), $payload);

    $numbers = Reimbursement::forTenant($this->tenant->id)
        ->orderBy('id')
        ->pluck('number')
        ->all();

    expect($numbers[0])->toEndWith('0001');
    expect($numbers[1])->toEndWith('0002');
});

it('accepts every finance category', function (string $category): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.reimbursement.store'), [
            'employee_id' => $employee->id,
            'category' => $category,
            'title' => 'Pengeluaran '.$category,
            'amount' => 100_000,
            'expense_date' => '2026-07-05',
        ])
        ->assertSessionHasNoErrors();

    expect(Reimbursement::forTenant($this->tenant->id)->where('category', $category)->exists())->toBeTrue();
})->with(['medical', 'komunikasi', 'transportasi', 'operasional', 'representasi']);

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.reimbursement.store'), [
            'employee_id' => '',
            'category' => '',
            'title' => '',
            'amount' => 0,
            'expense_date' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'category', 'title', 'amount', 'expense_date']);
});

it('rejects an unknown category', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.reimbursement.store'), [
            'employee_id' => $employee->id,
            'category' => 'liburan',
            'title' => 'Tiket konser',
            'amount' => 100_000,
            'expense_date' => '2026-07-05',
        ])
        ->assertSessionHasErrors(['category']);
});

it('rejects an expense dated in the future', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.reimbursement.store'), [
            'employee_id' => $employee->id,
            'category' => 'transportasi',
            'title' => 'Taksi besok',
            'amount' => 100_000,
            'expense_date' => now()->addWeek()->toDateString(),
        ])
        ->assertSessionHasErrors(['expense_date']);
});

it('approves a pending reimbursement and records the approver', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.approve', $reimbursement))
        ->assertSessionHas('success');

    $reimbursement->refresh();
    expect($reimbursement->status)->toBe('approved');
    expect((int) $reimbursement->approver_id)->toBe($this->admin->id);
    expect($reimbursement->approved_at)->not->toBeNull();
});

it('rejects a reimbursement with a reason', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.reject', $reimbursement), [
            'rejection_reason' => 'Struk tidak dilampirkan',
        ])
        ->assertSessionHas('success');

    $reimbursement->refresh();
    expect($reimbursement->status)->toBe('rejected');
    expect($reimbursement->rejection_reason)->toBe('Struk tidak dilampirkan');
});

it('requires a reason when rejecting', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.reject', $reimbursement), [])
        ->assertSessionHasErrors(['rejection_reason']);
});

it('pays an approved reimbursement and records how the money moved', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id, ['status' => 'approved']);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.pay', $reimbursement), [
            'payment_method' => 'transfer',
            'payment_reference' => 'TRF-4410',
        ])
        ->assertSessionHas('success');

    $reimbursement->refresh();
    expect($reimbursement->status)->toBe('paid');
    expect($reimbursement->payment_method)->toBe('transfer');
    expect($reimbursement->payment_reference)->toBe('TRF-4410');
    expect((int) $reimbursement->paid_by)->toBe($this->admin->id);
    expect($reimbursement->paid_at)->not->toBeNull();
});

it('refuses to pay a reimbursement that was never approved', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.pay', $reimbursement), [
            'payment_method' => 'transfer',
        ])
        ->assertStatus(422);

    expect($reimbursement->fresh()->status)->toBe('pending');
});

it('refuses to approve a reimbursement twice', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id, ['status' => 'approved']);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.approve', $reimbursement))
        ->assertStatus(422);
});

it('refuses to edit a reimbursement that has already been paid', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id, ['status' => 'paid']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.reimbursement.update', $reimbursement), [
            'employee_id' => $employee->id,
            'category' => 'transportasi',
            'title' => 'Diubah diam-diam',
            'amount' => 9_000_000,
            'expense_date' => '2026-07-05',
        ])
        ->assertStatus(422);

    expect((float) $reimbursement->fresh()->amount)->toBe(250_000.0);
});

it('updates a reimbursement that has not been paid', function (): void {
    $reimbursement = makeReimbursement($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.reimbursement.update', $reimbursement), [
            'employee_id' => $employee->id,
            'category' => 'representasi',
            'title' => 'Jamuan klien',
            'amount' => 480_000,
            'expense_date' => '2026-07-05',
        ])
        ->assertRedirect(route('avana.reimbursement'))
        ->assertSessionHas('success');

    $reimbursement->refresh();
    expect($reimbursement->category)->toBe('representasi');
    expect((float) $reimbursement->amount)->toBe(480_000.0);
});

it('deletes a reimbursement and its receipt', function (): void {
    Storage::fake('public');

    $receiptPath = UploadedFile::fake()->image('struk.jpg')->store('reimbursements', 'public');
    $reimbursement = makeReimbursement($this->tenant->id, ['receipt_path' => $receiptPath]);

    actingAs($this->admin)
        ->delete(route('avana.reimbursement.destroy', $reimbursement))
        ->assertSessionHas('success');

    expect(Reimbursement::find($reimbursement->id))->toBeNull();
    Storage::disk('public')->assertMissing($receiptPath);
});

it('filters the list by category', function (): void {
    makeReimbursement($this->tenant->id, ['number' => 'RMB-A', 'category' => 'medical']);
    makeReimbursement($this->tenant->id, ['number' => 'RMB-B', 'category' => 'transportasi']);

    actingAs($this->admin)
        ->get(route('avana.reimbursement', ['category' => 'medical']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', 1));
});

it('returns 404 when approving a reimbursement from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-rmb']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-6666',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = Reimbursement::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'number' => 'RMB-FOREIGN-0002',
        'category' => 'medical',
        'title' => 'Obat',
        'amount' => 100_000,
        'expense_date' => '2026-07-05',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.reimbursement.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids a plain employee from listing or creating reimbursements', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.reimbursement'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.reimbursement.store'), [
            'employee_id' => Employee::forTenant($this->tenant->id)->value('id'),
            'category' => 'medical',
            'title' => 'Obat',
            'amount' => 100_000,
            'expense_date' => '2026-07-05',
        ])
        ->assertForbidden();
});
