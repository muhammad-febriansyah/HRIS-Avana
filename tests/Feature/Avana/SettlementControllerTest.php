<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\Settlement;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
 * A settlement in the given status, carrying a single expense line worth
 * `$subtotal` (tax + total are recomputed from it).
 */
function makeSettlement(int $tenantId, string $status = Settlement::STATUS_SUBMITTED, float $subtotal = 1_000_000): Settlement
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    $settlement = Settlement::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'number' => 'STL-TEST-'.fake()->unique()->numberBetween(1000, 9999),
        'title' => 'Perjalanan Dinas Jakarta',
        'category' => 'transportasi',
        'submission_date' => '2026-07-18',
        'status' => $status,
    ]);

    $settlement->items()->create([
        'tenant_id' => $tenantId,
        'category' => 'transportasi',
        'description' => 'Tiket kereta',
        'amount' => $subtotal,
    ]);

    $settlement->recalculateTotals();

    return $settlement;
}

it('renders the settlement index with the expected props', function (): void {
    makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->get(route('avana.settlement'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/settlement/index', false)
            ->has('settlements.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('number')
                ->has('title')
                ->has('total')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('statusOptions')
            ->has('kpis.paid'));
});

it('creates a draft settlement with line items and applies 11% tax', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'employee_id' => $employee->id,
            'title' => 'Perjalanan Dinas Q3 Sales Summit',
            'category' => 'transportasi',
            'department' => 'Regional Sales',
            'submission_date' => '2026-07-18',
            'items' => [
                ['description' => 'Tiket pesawat', 'category' => 'transportasi', 'amount' => 2_500_000],
                ['description' => 'Hotel', 'category' => 'operasional', 'amount' => 1_800_000],
            ],
            'action' => 'draft',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settlement = Settlement::latest('id')->firstOrFail();

    expect($settlement->status)->toBe(Settlement::STATUS_DRAFT);
    expect($settlement->items)->toHaveCount(2);
    expect((float) $settlement->subtotal)->toBe(4_300_000.0);
    expect((float) $settlement->tax_amount)->toBe(473_000.0);
    expect((float) $settlement->total)->toBe(4_773_000.0);
    expect($settlement->number)->toStartWith('STL-');
});

it('submits straight to approval when the submit action is used', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'employee_id' => $employee->id,
            'title' => 'Perjalanan Dinas',
            'submission_date' => '2026-07-18',
            'items' => [['description' => 'Taksi', 'category' => 'transportasi', 'amount' => 350_000]],
            'action' => 'submit',
        ])
        ->assertSessionHas('success');

    expect(Settlement::latest('id')->firstOrFail()->status)->toBe(Settlement::STATUS_SUBMITTED);
});

it('snapshots the employee primary bank account onto the settlement', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    DB::table('employee_bank_accounts')->insert([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'bank_name' => 'Bank Central Asia',
        'account_number' => '1234567890',
        'account_holder' => 'Bagus Pratama',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'employee_id' => $employee->id,
            'title' => 'Perjalanan Dinas',
            'submission_date' => '2026-07-18',
            'items' => [['description' => 'Taksi', 'category' => 'transportasi', 'amount' => 350_000]],
            'action' => 'draft',
        ]);

    $settlement = Settlement::latest('id')->firstOrFail();

    expect($settlement->bank_name)->toBe('Bank Central Asia');
    expect($settlement->bank_account_number)->toBe('1234567890');
    expect($settlement->bank_account_holder)->toBe('Bagus Pratama');
});

it('rejects a settlement with no line items', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'employee_id' => $employee->id,
            'title' => 'Kosong',
            'submission_date' => '2026-07-18',
            'items' => [],
            'action' => 'draft',
        ])
        ->assertSessionHasErrors('items');
});

it('lets a manager approve a submitted settlement', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->post(route('avana.settlement.manager-approve', $settlement))
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->status)->toBe(Settlement::STATUS_MANAGER_APPROVED);
    expect($settlement->manager_approved_by)->toBe($this->admin->id);
    expect($settlement->manager_approved_at)->not->toBeNull();
});

it('lets Finance verify and pay a manager-approved settlement', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_MANAGER_APPROVED);

    actingAs($this->admin)
        ->post(route('avana.settlement.finance-verify', $settlement), [
            'payment_method' => 'transfer',
            'payment_reference' => 'TRF-20260718-1',
            'confirm_bank' => true,
            'confirm_receipts' => true,
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->status)->toBe(Settlement::STATUS_PAID);
    expect($settlement->finance_verified_by)->toBe($this->admin->id);
    expect($settlement->paid_at)->not->toBeNull();
    expect($settlement->payment_reference)->toBe('TRF-20260718-1');
});

it('requires the verification confirmations before paying', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_MANAGER_APPROVED);

    actingAs($this->admin)
        ->post(route('avana.settlement.finance-verify', $settlement), [
            'payment_method' => 'transfer',
            'confirm_bank' => false,
            'confirm_receipts' => false,
        ])
        ->assertSessionHasErrors(['confirm_bank', 'confirm_receipts']);

    expect($settlement->refresh()->status)->toBe(Settlement::STATUS_MANAGER_APPROVED);
});

it('will not let Finance pay before the manager has approved', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->post(route('avana.settlement.finance-verify', $settlement), [
            'payment_method' => 'transfer',
            'confirm_bank' => true,
            'confirm_receipts' => true,
        ])
        ->assertStatus(422);

    expect($settlement->refresh()->status)->toBe(Settlement::STATUS_SUBMITTED);
});

/**
 * A settlement submitted by an employee who reports to `$manager`, routed to
 * that manager's desk the way the submit paths do.
 */
function settlementForManager(int $tenantId, Employee $manager): Settlement
{
    $employee = Employee::forTenant($tenantId)->where('manager_id', $manager->id)->firstOrFail();

    $settlement = Settlement::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'current_approver_id' => $manager->id,
        'number' => 'STL-TEAM-'.fake()->unique()->numberBetween(1000, 9999),
        'title' => 'Kunjungan klien',
        'submission_date' => '2026-07-19',
        'status' => Settlement::STATUS_SUBMITTED,
    ]);

    $settlement->items()->create([
        'tenant_id' => $tenantId,
        'category' => 'transportasi',
        'description' => 'Taksi',
        'amount' => 300_000,
    ]);

    $settlement->recalculateTotals();

    return $settlement;
}

/** Grant the `manager` role (which carries team.claim.approve) to a user. */
function asLineManager(User $user, int $tenantId): User
{
    $role = Role::where('tenant_id', $tenantId)->where('code', 'manager')->firstOrFail();
    $user->roles()->sync([$role->id]);

    return $user->fresh();
}

it('lets a line manager approve a settlement from their own team', function (): void {
    $managerEmployee = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->whereHas('subordinates')
        ->firstOrFail();

    $manager = asLineManager(User::findOrFail($managerEmployee->user_id), $this->tenant->id);
    $settlement = settlementForManager($this->tenant->id, $managerEmployee);

    actingAs($manager)
        ->post(route('avana.settlement.manager-approve', $settlement))
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->status)->toBe(Settlement::STATUS_MANAGER_APPROVED);
    expect($settlement->manager_approved_by)->toBe($manager->id);
    expect($settlement->current_approver_id)->toBeNull();
});

it('hides another team\'s settlement from a line manager', function (): void {
    $managerEmployee = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->whereHas('subordinates')
        ->firstOrFail();

    $manager = asLineManager(User::findOrFail($managerEmployee->user_id), $this->tenant->id);

    // Routed to nobody — certainly not to this manager.
    $foreign = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($manager)
        ->get(route('avana.settlement.show', $foreign))
        ->assertForbidden();

    actingAs($manager)
        ->post(route('avana.settlement.manager-approve', $foreign))
        ->assertForbidden();

    expect($foreign->refresh()->status)->toBe(Settlement::STATUS_SUBMITTED);
});

it('keeps a line manager off the Finance desk entirely', function (): void {
    $managerEmployee = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->whereHas('subordinates')
        ->firstOrFail();

    $manager = asLineManager(User::findOrFail($managerEmployee->user_id), $this->tenant->id);
    $settlement = settlementForManager($this->tenant->id, $managerEmployee);

    actingAs($manager)->post(route('avana.settlement.manager-approve', $settlement));

    // Now manager-approved and off their desk: the payout is not theirs to make.
    actingAs($manager)
        ->post(route('avana.settlement.finance-verify', $settlement), [
            'payment_method' => 'transfer',
            'confirm_bank' => true,
            'confirm_receipts' => true,
        ])
        ->assertForbidden();

    actingAs($manager)
        ->get(route('avana.settlement.create'))
        ->assertForbidden();

    expect($settlement->refresh()->paid_at)->toBeNull();
});

it('blocks the manager who approved from also releasing the payout', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->post(route('avana.settlement.manager-approve', $settlement))
        ->assertSessionHas('success');

    actingAs($this->admin)
        ->post(route('avana.settlement.finance-verify', $settlement), [
            'payment_method' => 'transfer',
            'confirm_bank' => true,
            'confirm_receipts' => true,
        ])
        ->assertForbidden();

    expect($settlement->refresh()->status)->toBe(Settlement::STATUS_MANAGER_APPROVED);
    expect($settlement->paid_at)->toBeNull();
});

it('lets a second authorised person pay what someone else approved', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->post(route('avana.settlement.manager-approve', $settlement))
        ->assertSessionHas('success');

    $finance = User::where('email', 'dewi.f@nusantara.co.id')->firstOrFail();

    actingAs($finance)
        ->post(route('avana.settlement.finance-verify', $settlement), [
            'payment_method' => 'transfer',
            'confirm_bank' => true,
            'confirm_receipts' => true,
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->status)->toBe(Settlement::STATUS_PAID);
    expect($settlement->finance_verified_by)->toBe($finance->id);
    expect($settlement->manager_approved_by)->toBe($this->admin->id);
});

it('tells the approver on the detail screen why they cannot pay', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->post(route('avana.settlement.manager-approve', $settlement));

    actingAs($this->admin)
        ->get(route('avana.settlement.show', $settlement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selfApproved', true));

    $finance = User::where('email', 'dewi.f@nusantara.co.id')->firstOrFail();

    actingAs($finance)
        ->get(route('avana.settlement.show', $settlement))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('selfApproved', false));
});

it('sends a settlement back to the employee on reject', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_SUBMITTED);

    actingAs($this->admin)
        ->post(route('avana.settlement.reject', $settlement), [
            'rejection_reason' => 'Kuitansi hotel tidak jelas',
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->status)->toBe(Settlement::STATUS_REJECTED);
    expect($settlement->rejection_reason)->toBe('Kuitansi hotel tidak jelas');
});

it('submits a draft settlement for approval', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_DRAFT);

    actingAs($this->admin)
        ->post(route('avana.settlement.submit', $settlement))
        ->assertSessionHas('success');

    expect($settlement->refresh()->status)->toBe(Settlement::STATUS_SUBMITTED);
});

it('replaces the line items and recomputes totals on update', function (): void {
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_DRAFT, 1_000_000);
    $employee = $settlement->employee;

    actingAs($this->admin)
        ->post(route('avana.settlement.update', $settlement), [
            'employee_id' => $employee->id,
            'title' => 'Perjalanan Dinas (revisi)',
            'submission_date' => '2026-07-18',
            'items' => [['description' => 'Tiket revisi', 'category' => 'transportasi', 'amount' => 2_000_000]],
            'action' => 'draft',
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->items)->toHaveCount(1);
    expect((float) $settlement->subtotal)->toBe(2_000_000.0);
    expect((float) $settlement->total)->toBe(2_220_000.0);
});

it('deletes a draft settlement with its attachment files', function (): void {
    Storage::fake('public');
    $settlement = makeSettlement($this->tenant->id, Settlement::STATUS_DRAFT);
    $settlement->attachments()->create([
        'tenant_id' => $this->tenant->id,
        'path' => UploadedFile::fake()->create('receipt.pdf', 100)->store('settlements', 'public'),
        'original_name' => 'receipt.pdf',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.settlement.destroy', $settlement))
        ->assertRedirect(route('avana.settlement'));

    expect(Settlement::find($settlement->id))->toBeNull();
});

it('hides settlements from other tenants', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-stl']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-8888',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $settlement = Settlement::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'number' => 'STL-FOREIGN-0001',
        'title' => 'Perjalanan Dinas',
        'submission_date' => '2026-07-18',
        'status' => Settlement::STATUS_SUBMITTED,
    ]);

    actingAs($this->admin)
        ->get(route('avana.settlement.show', $settlement))
        ->assertNotFound();
});
