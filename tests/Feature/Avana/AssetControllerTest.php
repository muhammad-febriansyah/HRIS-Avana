<?php

use App\Models\Asset;
use App\Models\AssetAssignment;
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
 * Create an asset for the given tenant.
 */
function makeAsset(int $tenantId, array $overrides = []): Asset
{
    return Asset::factory()->create(array_merge([
        'tenant_id' => $tenantId,
    ], $overrides));
}

/**
 * Create an active (not yet returned) assignment for the given tenant.
 */
function makeAssignment(int $tenantId, array $overrides = []): AssetAssignment
{
    $asset = $overrides['asset_id'] ?? makeAsset($tenantId, ['status' => 'assigned'])->id;
    $employee = array_key_exists('employee_id', $overrides)
        ? $overrides['employee_id']
        : Employee::forTenant($tenantId)->firstOrFail()->id;

    return AssetAssignment::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'asset_id' => $asset,
        'employee_id' => $employee,
        'returned_date' => null,
    ], $overrides));
}

it('renders the asset index with the expected props', function (): void {
    makeAsset($this->tenant->id);
    makeAssignment($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.aset'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/aset/index', false)
            ->has('assets.0', fn (Assert $row) => $row
                ->has('id')
                ->has('code')
                ->has('name')
                ->has('category')
                ->has('purchase_date')
                ->has('purchase_cost')
                ->has('depreciation_years')
                ->has('condition')
                ->has('status')
                ->has('notes')
                ->has('book_value')
                ->has('current_assignment'))
            ->has('assignments.0', fn (Assert $row) => $row
                ->has('id')
                ->has('asset')
                ->has('employee')
                ->has('assigned_date')
                ->has('condition_note'))
            ->has('employees')
            ->has('categories')
            ->has('conditions')
            ->has('statuses')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->has('total')
                ->has('available')
                ->has('assigned')
                ->has('maintenance')
                ->has('total_value')));
});

it('only lists assets that belong to the current tenant', function (): void {
    makeAsset($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeAsset($otherTenant->id);

    $tenantTotal = Asset::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.aset'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assets', $tenantTotal));
});

it('creates an asset scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.aset.store'), [
            'code' => 'AST-100',
            'name' => 'Laptop Dell Latitude',
            'category' => 'Elektronik',
            'purchase_date' => '2026-01-15',
            'purchase_cost' => 15_000_000,
            'depreciation_years' => 4,
            'condition' => 'good',
            'status' => 'available',
            'notes' => 'Unit baru',
        ])
        ->assertRedirect(route('avana.aset'))
        ->assertSessionHas('success');

    $asset = Asset::where('code', 'AST-100')->firstOrFail();

    expect($asset->tenant_id)->toBe($this->tenant->id);
    expect($asset->category)->toBe('Elektronik');
    expect((float) $asset->purchase_cost)->toBe(15_000_000.0);
    expect($asset->status)->toBe('available');
});

it('validates required fields and duplicate code on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.aset.store'), [
            'code' => '',
            'name' => '',
            'category' => '',
            'purchase_cost' => -5,
            'depreciation_years' => 0,
            'condition' => 'invalid',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors([
            'code',
            'name',
            'category',
            'purchase_cost',
            'depreciation_years',
            'condition',
            'status',
        ]);

    makeAsset($this->tenant->id, ['code' => 'AST-DUP']);

    actingAs($this->admin)
        ->post(route('avana.aset.store'), [
            'code' => 'AST-DUP',
            'name' => 'Duplikat',
            'category' => 'Elektronik',
            'purchase_cost' => 100,
            'depreciation_years' => 3,
            'condition' => 'good',
            'status' => 'available',
        ])
        ->assertSessionHasErrors(['code']);
});

it('updates an existing asset', function (): void {
    $asset = makeAsset($this->tenant->id, ['code' => 'AST-OLD']);

    actingAs($this->admin)
        ->put(route('avana.aset.update', $asset), [
            'code' => 'AST-NEW',
            'name' => 'Monitor LG',
            'category' => 'Furnitur',
            'purchase_date' => null,
            'purchase_cost' => 2_500_000,
            'depreciation_years' => 5,
            'condition' => 'fair',
            'status' => 'maintenance',
            'notes' => null,
        ])
        ->assertRedirect(route('avana.aset'))
        ->assertSessionHas('success');

    $asset->refresh();

    expect($asset->code)->toBe('AST-NEW');
    expect($asset->name)->toBe('Monitor LG');
    expect($asset->category)->toBe('Furnitur');
    expect($asset->condition)->toBe('fair');
    expect($asset->status)->toBe('maintenance');
});

it('deletes an asset', function (): void {
    $asset = makeAsset($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.aset.destroy', $asset))
        ->assertSessionHas('success');

    expect(Asset::find($asset->id))->toBeNull();
});

it('assigns an asset to an employee and marks it as assigned', function (): void {
    $asset = makeAsset($this->tenant->id, ['status' => 'available']);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.aset.assign', $asset), [
            'employee_id' => $employee->id,
            'assigned_date' => '2026-07-01',
            'condition_note' => 'Diserahkan dalam kondisi baik',
        ])
        ->assertRedirect(route('avana.aset'))
        ->assertSessionHas('success');

    $assignment = AssetAssignment::where('asset_id', $asset->id)
        ->where('employee_id', $employee->id)
        ->firstOrFail();

    expect($assignment->tenant_id)->toBe($this->tenant->id);
    expect($assignment->returned_date)->toBeNull();

    $asset->refresh();

    expect($asset->status)->toBe('assigned');
});

it('validates the assign request against tenant-scoped employees', function (): void {
    $asset = makeAsset($this->tenant->id, ['status' => 'available']);

    actingAs($this->admin)
        ->post(route('avana.aset.assign', $asset), [
            'employee_id' => 99999,
            'assigned_date' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'assigned_date']);
});

it('returns an asset and frees it back to available', function (): void {
    $asset = makeAsset($this->tenant->id, ['status' => 'assigned']);
    $assignment = makeAssignment($this->tenant->id, ['asset_id' => $asset->id]);

    actingAs($this->admin)
        ->post(route('avana.aset.return', $assignment), [
            'returned_date' => '2026-07-10',
            'condition_note' => 'Dikembalikan tanpa kerusakan',
        ])
        ->assertSessionHas('success');

    $assignment->refresh();
    $asset->refresh();

    expect($assignment->returned_date)->not->toBeNull();
    expect($asset->status)->toBe('available');
});

it('returns 404 when updating an asset from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeAsset($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.aset.update', $foreign), [
            'code' => 'AST-HACK',
            'name' => 'Hack',
            'category' => 'Elektronik',
            'purchase_cost' => 1,
            'depreciation_years' => 1,
            'condition' => 'good',
            'status' => 'available',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting an asset from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeAsset($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.aset.destroy', $foreign))
        ->assertNotFound();

    expect(Asset::find($foreign->id))->not->toBeNull();
});

it('returns 404 when assigning an asset from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeAsset($otherTenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.aset.assign', $foreign), [
            'employee_id' => $employee->id,
            'assigned_date' => '2026-07-01',
        ])
        ->assertNotFound();
});

it('returns 404 when returning an assignment from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = makeAssignment($otherTenant->id, [
        'employee_id' => $foreignEmployee->id,
    ]);

    actingAs($this->admin)
        ->post(route('avana.aset.return', $foreign), [
            'returned_date' => '2026-07-10',
        ])
        ->assertNotFound();

    $foreign->refresh();

    expect($foreign->returned_date)->toBeNull();
});

it('forbids a plain employee from listing or creating assets', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.aset'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.aset.store'), [
            'code' => 'AST-NO',
            'name' => 'Tidak Boleh',
            'category' => 'Elektronik',
            'purchase_cost' => 100,
            'depreciation_years' => 3,
            'condition' => 'good',
            'status' => 'available',
        ])
        ->assertForbidden();
});
