<?php

use App\Models\ApprovalDelegation;
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
 * Return two distinct employee ids for the given tenant.
 *
 * @return array{0: int, 1: int}
 */
function delegationPair(int $tenantId): array
{
    $ids = Employee::forTenant($tenantId)->orderBy('id')->take(2)->pluck('id')->all();

    return [$ids[0], $ids[1]];
}

/**
 * Create an approval delegation for the given tenant.
 */
function makeDelegation(int $tenantId, array $overrides = []): ApprovalDelegation
{
    [$delegator, $delegate] = delegationPair($tenantId);

    return ApprovalDelegation::create(array_merge([
        'tenant_id' => $tenantId,
        'delegator_id' => $delegator,
        'delegate_id' => $delegate,
        'scope' => 'all',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ], $overrides));
}

it('renders the delegasi index with the expected props', function (): void {
    makeDelegation($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.delegasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/delegasi/index', false)
            ->has('delegations.0', fn (Assert $row) => $row
                ->has('id')
                ->has('delegator.name')
                ->has('delegate.name')
                ->has('scope')
                ->has('scope_label')
                ->has('start_date')
                ->has('end_date')
                ->has('is_active'))
            ->has('employees')
            ->has('scopes')
            ->has('kpis.active_delegations'));
});

it('only lists delegations that belong to the current tenant', function (): void {
    makeDelegation($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $a = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-A',
        'full_name' => 'Delegator Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $b = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-B',
        'full_name' => 'Delegate Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    ApprovalDelegation::create([
        'tenant_id' => $otherTenant->id,
        'delegator_id' => $a->id,
        'delegate_id' => $b->id,
        'scope' => 'leave',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    $tenantTotal = ApprovalDelegation::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.delegasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('delegations', $tenantTotal));
});

it('creates a delegation scoped to the current tenant', function (): void {
    [$delegator, $delegate] = delegationPair($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.delegasi.store'), [
            'delegator_id' => $delegator,
            'delegate_id' => $delegate,
            'scope' => 'leave',
            'start_date' => '2026-07-05',
            'end_date' => '2026-07-20',
        ])
        ->assertRedirect(route('avana.delegasi'))
        ->assertSessionHas('success');

    $delegation = ApprovalDelegation::where('delegator_id', $delegator)->latest('id')->firstOrFail();

    expect($delegation->tenant_id)->toBe($this->tenant->id);
    expect($delegation->scope)->toBe('leave');
    expect($delegation->is_active)->toBeTrue();
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.delegasi.store'), [
            'delegator_id' => '',
            'delegate_id' => '',
            'scope' => 'invalid',
            'start_date' => '',
            'end_date' => '',
        ])
        ->assertSessionHasErrors(['delegator_id', 'delegate_id', 'scope', 'start_date', 'end_date']);
});

it('rejects a delegation where delegate equals delegator', function (): void {
    [$delegator] = delegationPair($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.delegasi.store'), [
            'delegator_id' => $delegator,
            'delegate_id' => $delegator,
            'scope' => 'all',
            'start_date' => '2026-07-05',
            'end_date' => '2026-07-20',
        ])
        ->assertSessionHasErrors(['delegate_id']);
});

it('toggles the active flag of a delegation', function (): void {
    $delegation = makeDelegation($this->tenant->id, ['is_active' => true]);

    actingAs($this->admin)
        ->post(route('avana.delegasi.toggle', $delegation))
        ->assertSessionHas('success');

    expect($delegation->fresh()->is_active)->toBeFalse();

    actingAs($this->admin)
        ->post(route('avana.delegasi.toggle', $delegation))
        ->assertSessionHas('success');

    expect($delegation->fresh()->is_active)->toBeTrue();
});

it('deletes a delegation', function (): void {
    $delegation = makeDelegation($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.delegasi.destroy', $delegation))
        ->assertSessionHas('success');

    expect(ApprovalDelegation::find($delegation->id))->toBeNull();
});

it('returns 404 when toggling a delegation from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $a = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-A',
        'full_name' => 'Delegator Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $b = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-B',
        'full_name' => 'Delegate Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = ApprovalDelegation::create([
        'tenant_id' => $otherTenant->id,
        'delegator_id' => $a->id,
        'delegate_id' => $b->id,
        'scope' => 'all',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'is_active' => true,
    ]);

    actingAs($this->admin)
        ->post(route('avana.delegasi.toggle', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->is_active)->toBeTrue();
});

it('forbids a plain employee from listing or creating delegations', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.delegasi'))
        ->assertForbidden();

    [$delegator, $delegate] = delegationPair($this->tenant->id);

    actingAs($staff)
        ->post(route('avana.delegasi.store'), [
            'delegator_id' => $delegator,
            'delegate_id' => $delegate,
            'scope' => 'all',
            'start_date' => '2026-07-05',
            'end_date' => '2026-07-20',
        ])
        ->assertForbidden();
});
