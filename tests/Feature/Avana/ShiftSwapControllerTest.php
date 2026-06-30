<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\ShiftSwap;
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
 * Create a minimal active employee for the given tenant.
 */
function makeSwapEmployee(int $tenantId, array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
        'full_name' => fake()->name(),
        'status' => 'active',
    ], $overrides));
}

/**
 * Create a shift swap request for the given tenant.
 */
function makeShiftSwap(int $tenantId, array $overrides = []): ShiftSwap
{
    $employees = Employee::forTenant($tenantId)->take(2)->pluck('id')->all();
    $requesterId = $overrides['requester_id'] ?? ($employees[0] ?? makeSwapEmployee($tenantId)->id);
    $targetId = $overrides['target_id'] ?? ($employees[1] ?? makeSwapEmployee($tenantId)->id);

    return ShiftSwap::create(array_merge([
        'tenant_id' => $tenantId,
        'requester_id' => $requesterId,
        'target_id' => $targetId,
        'date' => '2026-07-10',
        'reason' => 'Acara keluarga',
        'status' => 'pending',
    ], $overrides));
}

it('renders the shift swap index with the expected props', function (): void {
    makeShiftSwap($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.shift-swap'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/shift-swap/index', false)
            ->has('swaps.0', fn (Assert $row) => $row
                ->has('id')
                ->has('requester')
                ->has('requester_id')
                ->has('target')
                ->has('target_id')
                ->has('date')
                ->has('requester_shift')
                ->has('requester_shift_id')
                ->has('target_shift')
                ->has('target_shift_id')
                ->has('reason')
                ->has('status')
                ->has('status_label'))
            ->has('employees')
            ->has('shifts')
            ->has('kpis'));
});

it('only lists shift swaps that belong to the current tenant', function (): void {
    makeShiftSwap($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeShiftSwap($otherTenant->id, [
        'requester_id' => makeSwapEmployee($otherTenant->id)->id,
        'target_id' => makeSwapEmployee($otherTenant->id)->id,
    ]);

    $tenantTotal = ShiftSwap::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.shift-swap'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('swaps', $tenantTotal));
});

it('creates a shift swap scoped to the current tenant', function (): void {
    $employees = Employee::forTenant($this->tenant->id)->take(2)->pluck('id')->all();

    actingAs($this->admin)
        ->post(route('avana.shift-swap.store'), [
            'requester_id' => $employees[0],
            'target_id' => $employees[1],
            'date' => '2026-07-15',
            'reason' => 'Tukar jadwal',
        ])
        ->assertRedirect(route('avana.shift-swap'))
        ->assertSessionHas('success');

    $swap = ShiftSwap::where('reason', 'Tukar jadwal')->firstOrFail();

    expect($swap->tenant_id)->toBe($this->tenant->id);
    expect($swap->status)->toBe('pending');
    expect($swap->requester_id)->toBe($employees[0]);
    expect($swap->target_id)->toBe($employees[1]);
});

it('validates the shift swap store request', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.shift-swap.store'), [
            'requester_id' => $employee->id,
            'target_id' => $employee->id,
            'date' => '',
        ])
        ->assertSessionHasErrors(['target_id', 'date']);
});

it('approves a pending shift swap', function (): void {
    $swap = makeShiftSwap($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.shift-swap.approve', $swap))
        ->assertSessionHas('success');

    expect($swap->fresh()->status)->toBe('approved');
});

it('rejects a pending shift swap', function (): void {
    $swap = makeShiftSwap($this->tenant->id);

    actingAs($this->admin)
        ->post(route('avana.shift-swap.reject', $swap))
        ->assertSessionHas('success');

    expect($swap->fresh()->status)->toBe('rejected');
});

it('deletes a shift swap', function (): void {
    $swap = makeShiftSwap($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.shift-swap.destroy', $swap))
        ->assertSessionHas('success');

    expect(ShiftSwap::find($swap->id))->toBeNull();
});

it('returns 404 when approving a shift swap from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeShiftSwap($otherTenant->id, [
        'requester_id' => makeSwapEmployee($otherTenant->id)->id,
        'target_id' => makeSwapEmployee($otherTenant->id)->id,
    ]);

    actingAs($this->admin)
        ->post(route('avana.shift-swap.approve', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('pending');
});

it('forbids a plain employee from listing shift swaps', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.shift-swap'))
        ->assertForbidden();
});
