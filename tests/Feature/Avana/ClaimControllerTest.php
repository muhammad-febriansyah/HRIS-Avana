<?php

use App\Models\Claim;
use App\Models\Employee;
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

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a claim for the given tenant, auto-provisioning an employee.
 */
function makeClaim(int $tenantId, array $overrides = []): Claim
{
    return Claim::factory()->create(array_merge([
        'tenant_id' => $tenantId,
    ], $overrides));
}

it('renders the claim index with the expected props', function (): void {
    makeClaim($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.klaim'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/klaim/index', false)
            ->has('claims.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee')
                ->has('employee_id')
                ->has('claim_type')
                ->has('title')
                ->has('amount')
                ->has('claim_date')
                ->has('description')
                ->has('receipt_url')
                ->has('status')
                ->has('notes')
                ->has('approver')
                ->has('approved_at'))
            ->has('employees')
            ->has('claimTypes')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->has('pending')
                ->has('approved')
                ->has('paid')
                ->has('total_amount')));
});

it('only lists claims that belong to the current tenant', function (): void {
    makeClaim($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeClaim($otherTenant->id);

    $tenantTotal = Claim::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.klaim'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('claims', $tenantTotal));
});

it('creates a claim with an uploaded receipt scoped to the current tenant', function (): void {
    Storage::fake('public');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.klaim.store'), [
            'employee_id' => $employee->id,
            'claim_type' => 'medical',
            'title' => 'Periksa Gigi',
            'amount' => 350000,
            'claim_date' => '2026-07-01',
            'description' => 'Tambal gigi',
            'notes' => 'Lampiran kuitansi',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])
        ->assertRedirect(route('avana.klaim'))
        ->assertSessionHas('success');

    $claim = Claim::where('title', 'Periksa Gigi')->firstOrFail();

    expect($claim->tenant_id)->toBe($this->tenant->id);
    expect($claim->employee_id)->toBe($employee->id);
    expect($claim->claim_type)->toBe('medical');
    expect($claim->status)->toBe('pending');
    expect($claim->receipt_path)->not->toBeNull();

    Storage::disk('public')->assertExists($claim->receipt_path);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.klaim.store'), [
            'employee_id' => 99999,
            'claim_type' => 'invalid',
            'title' => '',
            'amount' => 'abc',
            'claim_date' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'claim_type', 'title', 'amount', 'claim_date']);
});

it('updates an existing claim', function (): void {
    $claim = makeClaim($this->tenant->id, ['title' => 'Old Title']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.klaim.update', $claim), [
            'employee_id' => $employee->id,
            'claim_type' => 'transport',
            'title' => 'New Title',
            'amount' => 120000,
            'claim_date' => '2026-07-02',
            'description' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('avana.klaim'))
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->title)->toBe('New Title');
    expect($claim->claim_type)->toBe('transport');
    expect((float) $claim->amount)->toBe(120000.0);
});

it('deletes a claim', function (): void {
    $claim = makeClaim($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.klaim.destroy', $claim))
        ->assertSessionHas('success');

    expect(Claim::find($claim->id))->toBeNull();
});

it('approves a pending claim', function (): void {
    $claim = makeClaim($this->tenant->id, ['status' => 'pending']);

    actingAs($this->admin)
        ->post(route('avana.klaim.approve', $claim))
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->status)->toBe('approved');
    expect($claim->approver_id)->toBe($this->admin->id);
    expect($claim->approved_at)->not->toBeNull();
});

it('rejects a pending claim', function (): void {
    $claim = makeClaim($this->tenant->id, ['status' => 'pending']);

    actingAs($this->admin)
        ->post(route('avana.klaim.reject', $claim))
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->status)->toBe('rejected');
    expect($claim->approver_id)->toBe($this->admin->id);
    expect($claim->approved_at)->not->toBeNull();
});

it('marks an approved claim as paid', function (): void {
    $claim = makeClaim($this->tenant->id, ['status' => 'approved']);

    actingAs($this->admin)
        ->post(route('avana.klaim.pay', $claim))
        ->assertSessionHas('success');

    $claim->refresh();

    expect($claim->status)->toBe('paid');
});

it('returns 404 when updating a claim from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeClaim($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.klaim.update', $foreign), [
            'employee_id' => $foreign->employee_id,
            'claim_type' => 'medical',
            'title' => 'Hack',
            'amount' => 1000,
            'claim_date' => '2026-07-01',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a claim from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeClaim($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.klaim.destroy', $foreign))
        ->assertNotFound();

    expect(Claim::find($foreign->id))->not->toBeNull();
});

it('returns 404 when approving a claim from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeClaim($otherTenant->id, ['status' => 'pending']);

    actingAs($this->admin)
        ->post(route('avana.klaim.approve', $foreign))
        ->assertNotFound();

    $foreign->refresh();

    expect($foreign->status)->toBe('pending');
});

it('forbids a plain employee from listing or creating claims', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.klaim'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.klaim.store'), [
            'employee_id' => 1,
            'claim_type' => 'medical',
            'title' => 'Tidak Boleh',
            'amount' => 1000,
            'claim_date' => '2026-07-01',
        ])
        ->assertForbidden();
});
