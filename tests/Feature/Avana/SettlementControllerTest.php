<?php

use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Settlement;
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
 * A disbursed advance, the only state a settlement can be opened against.
 */
function makeDisbursedAdvance(int $tenantId, float $amount = 2_000_000): CashAdvance
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return CashAdvance::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'amount' => $amount,
        'purpose' => 'Uang muka perjalanan dinas',
        'request_date' => '2026-07-01',
        'status' => 'disbursed',
        'disbursed_at' => now(),
    ]);
}

/**
 * A settlement carrying a single receipt line worth `$spent`.
 */
function makeSettlementSpending(int $tenantId, float $advanceAmount, float $spent, string $status = 'draft'): Settlement
{
    $advance = makeDisbursedAdvance($tenantId, $advanceAmount);

    $settlement = Settlement::create([
        'tenant_id' => $tenantId,
        'cash_advance_id' => $advance->id,
        'employee_id' => $advance->employee_id,
        'number' => 'STL-TEST-0001',
        'settlement_date' => '2026-07-10',
        'advance_amount' => $advanceAmount,
        'total_spent' => 0,
        'status' => $status,
    ]);

    $settlement->items()->create([
        'tenant_id' => $tenantId,
        'category' => 'transportasi',
        'description' => 'Tiket kereta',
        'spent_date' => '2026-07-05',
        'amount' => $spent,
    ]);

    $settlement->recalculateTotalSpent();

    return $settlement;
}

it('renders the settlement index with the expected props', function (): void {
    makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000);

    actingAs($this->admin)
        ->get(route('avana.settlement'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/settlement/index', false)
            ->has('settlements.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('number')
                ->has('advance_amount')
                ->has('total_spent')
                ->has('balance')
                ->has('outcome')
                ->has('outcome_label')
                ->has('status_label')
                ->etc())
            ->has('filters')
            ->has('statusOptions')
            ->has('kpis.draft'));
});

it('opens a settlement against a disbursed advance and snapshots its amount', function (): void {
    $advance = makeDisbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'cash_advance_id' => $advance->id,
            'settlement_date' => '2026-07-10',
        ])
        ->assertSessionHas('success');

    $settlement = Settlement::where('cash_advance_id', $advance->id)->firstOrFail();

    expect($settlement->status)->toBe('draft');
    expect((float) $settlement->advance_amount)->toBe(2_000_000.0);
    expect($settlement->employee_id)->toBe($advance->employee_id);
    expect($settlement->number)->toStartWith('STL-');
});

it('snapshots the advance amount so later edits cannot move the balance', function (): void {
    $advance = makeDisbursedAdvance($this->tenant->id, 2_000_000);

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'cash_advance_id' => $advance->id,
            'settlement_date' => '2026-07-10',
        ]);

    $advance->update(['amount' => 5_000_000]);

    $settlement = Settlement::where('cash_advance_id', $advance->id)->firstOrFail();

    expect((float) $settlement->advance_amount)->toBe(2_000_000.0);
});

it('refuses to open a settlement against an advance that was never disbursed', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $advance = CashAdvance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'amount' => 1_000_000,
        'purpose' => 'Uang muka',
        'request_date' => '2026-07-01',
        'status' => 'approved',
    ]);

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'cash_advance_id' => $advance->id,
            'settlement_date' => '2026-07-10',
        ])
        ->assertStatus(422);

    expect(Settlement::where('cash_advance_id', $advance->id)->exists())->toBeFalse();
});

it('refuses to open a second settlement for the same advance', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_000_000);

    actingAs($this->admin)
        ->post(route('avana.settlement.store'), [
            'cash_advance_id' => $settlement->cash_advance_id,
            'settlement_date' => '2026-07-11',
        ])
        ->assertSessionHasErrors(['cash_advance_id']);

    expect(Settlement::where('cash_advance_id', $settlement->cash_advance_id)->count())->toBe(1);
});

it('adds a receipt line and recalculates the total spent', function (): void {
    Storage::fake('public');

    $advance = makeDisbursedAdvance($this->tenant->id, 2_000_000);
    $settlement = Settlement::create([
        'tenant_id' => $this->tenant->id,
        'cash_advance_id' => $advance->id,
        'employee_id' => $advance->employee_id,
        'number' => 'STL-TEST-0002',
        'settlement_date' => '2026-07-10',
        'advance_amount' => 2_000_000,
        'status' => 'draft',
    ]);

    actingAs($this->admin)
        ->post(route('avana.settlement.items.store', $settlement), [
            'category' => 'transportasi',
            'description' => 'Tiket kereta Jakarta–Surabaya',
            'spent_date' => '2026-07-05',
            'amount' => 750_000,
            'receipt' => UploadedFile::fake()->image('struk.jpg'),
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->items)->toHaveCount(1);
    expect((float) $settlement->total_spent)->toBe(750_000.0);
    expect($settlement->items->first()->receipt_path)->not->toBeNull();
    Storage::disk('public')->assertExists($settlement->items->first()->receipt_path);
});

it('refuses to submit a settlement with no receipts', function (): void {
    $advance = makeDisbursedAdvance($this->tenant->id, 2_000_000);
    $settlement = Settlement::create([
        'tenant_id' => $this->tenant->id,
        'cash_advance_id' => $advance->id,
        'employee_id' => $advance->employee_id,
        'number' => 'STL-TEST-0003',
        'settlement_date' => '2026-07-10',
        'advance_amount' => 2_000_000,
        'status' => 'draft',
    ]);

    actingAs($this->admin)
        ->post(route('avana.settlement.submit', $settlement))
        ->assertStatus(422);

    expect($settlement->fresh()->status)->toBe('draft');
});

it('computes a leftover balance as a return owed by the employee', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000);

    expect($settlement->balance())->toBe(500_000.0);
    expect($settlement->outcome())->toBe(Settlement::OUTCOME_RETURN);
    expect($settlement->outstanding())->toBe(500_000.0);
});

it('computes an overspend as a topup owed to the employee', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 2_300_000);

    expect($settlement->balance())->toBe(-300_000.0);
    expect($settlement->outcome())->toBe(Settlement::OUTCOME_TOPUP);
    expect($settlement->outstanding())->toBe(300_000.0);
});

it('closes a balanced settlement the moment it is approved', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 2_000_000, 'submitted');

    actingAs($this->admin)
        ->post(route('avana.settlement.approve', $settlement))
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->outcome())->toBe(Settlement::OUTCOME_BALANCED);
    expect($settlement->status)->toBe('closed');
    expect($settlement->cashAdvance->status)->toBe('settled');
    expect($settlement->cashAdvance->settled_at)->not->toBeNull();
});

it('keeps a settlement with a difference open until the money moves', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'submitted');

    actingAs($this->admin)
        ->post(route('avana.settlement.approve', $settlement))
        ->assertSessionHas('success');

    $settlement->refresh();

    expect($settlement->status)->toBe('approved');
    expect($settlement->cashAdvance->status)->toBe('disbursed');
});

it('closes the settlement and the advance once the leftover is returned in full', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.return', $settlement), [
            'returned_amount' => 500_000,
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect((float) $settlement->returned_amount)->toBe(500_000.0);
    expect($settlement->outstanding())->toBe(0.0);
    expect($settlement->status)->toBe('closed');
    expect($settlement->cashAdvance->status)->toBe('settled');
});

it('accumulates partial returns and only closes on the last one', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.return', $settlement), ['returned_amount' => 200_000]);

    $settlement->refresh();
    expect((float) $settlement->returned_amount)->toBe(200_000.0);
    expect($settlement->outstanding())->toBe(300_000.0);
    expect($settlement->status)->toBe('approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.return', $settlement), ['returned_amount' => 300_000]);

    $settlement->refresh();
    expect((float) $settlement->returned_amount)->toBe(500_000.0);
    expect($settlement->status)->toBe('closed');
});

it('refuses to return more than the leftover', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.return', $settlement), [
            'returned_amount' => 600_000,
        ])
        ->assertSessionHasErrors(['returned_amount']);

    expect((float) $settlement->fresh()->returned_amount)->toBe(0.0);
});

it('closes the settlement once the shortfall is paid to the employee', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 2_300_000, 'approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.topup', $settlement), [
            'topup_amount' => 300_000,
            'topup_method' => 'transfer',
            'topup_reference' => 'TRF-5512',
        ])
        ->assertSessionHas('success');

    $settlement->refresh();

    expect((float) $settlement->topup_amount)->toBe(300_000.0);
    expect($settlement->topup_reference)->toBe('TRF-5512');
    expect($settlement->status)->toBe('closed');
    expect($settlement->cashAdvance->status)->toBe('settled');
});

it('refuses a topup on a settlement that has leftover instead of a shortfall', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.topup', $settlement), [
            'topup_amount' => 100_000,
            'topup_method' => 'transfer',
        ])
        ->assertStatus(422);
});

it('refuses a return on a settlement that has a shortfall instead of leftover', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 2_300_000, 'approved');

    actingAs($this->admin)
        ->post(route('avana.settlement.return', $settlement), [
            'returned_amount' => 100_000,
        ])
        ->assertStatus(422);
});

it('refuses to settle the difference before the settlement is approved', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'submitted');

    actingAs($this->admin)
        ->post(route('avana.settlement.return', $settlement), [
            'returned_amount' => 500_000,
        ])
        ->assertStatus(422);
});

it('sends a submitted settlement back with a reason', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'submitted');

    actingAs($this->admin)
        ->post(route('avana.settlement.reject', $settlement), [
            'rejection_reason' => 'Struk tiket tidak terbaca',
        ])
        ->assertSessionHas('success');

    $settlement->refresh();
    expect($settlement->status)->toBe('rejected');
    expect($settlement->rejection_reason)->toBe('Struk tiket tidak terbaca');
});

it('requires a reason when rejecting', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'submitted');

    actingAs($this->admin)
        ->post(route('avana.settlement.reject', $settlement), [])
        ->assertSessionHasErrors(['rejection_reason']);
});

it('refuses to change receipts once the settlement is being verified', function (): void {
    $settlement = makeSettlementSpending($this->tenant->id, 2_000_000, 1_500_000, 'submitted');

    actingAs($this->admin)
        ->post(route('avana.settlement.items.store', $settlement), [
            'category' => 'operasional',
            'description' => 'Tambahan diam-diam',
            'spent_date' => '2026-07-06',
            'amount' => 100_000,
        ])
        ->assertStatus(422);

    expect($settlement->fresh()->items)->toHaveCount(1);
});

it('returns 404 for a settlement that belongs to another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-stl']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreignAdvance = CashAdvance::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'amount' => 1_000_000,
        'purpose' => 'Uang muka asing',
        'request_date' => '2026-07-01',
        'status' => 'disbursed',
    ]);
    $foreign = Settlement::create([
        'tenant_id' => $otherTenant->id,
        'cash_advance_id' => $foreignAdvance->id,
        'employee_id' => $foreignEmployee->id,
        'number' => 'STL-FOREIGN-0001',
        'settlement_date' => '2026-07-10',
        'advance_amount' => 1_000_000,
        'status' => 'draft',
    ]);

    actingAs($this->admin)
        ->get(route('avana.settlement.show', $foreign))
        ->assertNotFound();
});

it('forbids a plain employee from managing settlements', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.settlement'))
        ->assertForbidden();
});
