<?php

use App\Models\Budget;
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

it('renders the budget index with the expected props', function (): void {
    Budget::create([
        'tenant_id' => $this->tenant->id,
        'category' => 'training',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 10_000_000,
        'actual_amount' => 6_000_000,
    ]);

    actingAs($this->admin)
        ->get(route('avana.anggaran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/anggaran/index', false)
            ->has('categories')
            ->has('periodTypes')
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->has('total_planned')
                ->has('total_actual')
                ->has('usage_percent'))
            ->has('budgets', 1, fn (Assert $row) => $row
                ->has('id')
                ->has('category')
                ->has('period_type')
                ->has('period')
                ->has('planned_amount')
                ->has('actual_amount')
                ->has('variance')
                ->has('variance_percent')
                ->has('notes')));
});

it('computes variance and usage percent', function (): void {
    Budget::create([
        'tenant_id' => $this->tenant->id,
        'category' => 'operational',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 10_000_000,
        'actual_amount' => 4_000_000,
    ]);

    actingAs($this->admin)
        ->get(route('avana.anggaran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('budgets.0.variance', 6_000_000)
            ->where('budgets.0.variance_percent', 60)
            ->where('kpis.usage_percent', 40));
});

it('only lists budgets that belong to the current tenant', function (): void {
    Budget::create([
        'tenant_id' => $this->tenant->id,
        'category' => 'payroll',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 1000,
        'actual_amount' => 0,
    ]);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    Budget::create([
        'tenant_id' => $otherTenant->id,
        'category' => 'payroll',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 9999,
        'actual_amount' => 0,
    ]);

    actingAs($this->admin)
        ->get(route('avana.anggaran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('budgets', 1));
});

it('creates a budget scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.anggaran.store'), [
            'category' => 'recruitment',
            'period_type' => 'yearly',
            'period' => '2026',
            'planned_amount' => 50_000_000,
            'actual_amount' => 12_000_000,
            'notes' => 'Anggaran rekrutmen tahunan',
        ])
        ->assertSessionHas('success');

    $budget = Budget::where('category', 'recruitment')->firstOrFail();

    expect($budget->tenant_id)->toBe($this->tenant->id);
    expect((float) $budget->planned_amount)->toBe(50_000_000.0);
    expect($budget->period_type)->toBe('yearly');
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.anggaran.store'), [
            'category' => 'invalid',
            'period_type' => 'invalid',
            'period' => '',
            'planned_amount' => 'abc',
            'actual_amount' => -5,
        ])
        ->assertSessionHasErrors(['category', 'period_type', 'period', 'planned_amount', 'actual_amount']);
});

it('updates a budget', function (): void {
    $budget = Budget::create([
        'tenant_id' => $this->tenant->id,
        'category' => 'benefit',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 1_000_000,
        'actual_amount' => 0,
    ]);

    actingAs($this->admin)
        ->put(route('avana.anggaran.update', $budget), [
            'category' => 'benefit',
            'period_type' => 'monthly',
            'period' => '2026-08',
            'planned_amount' => 2_000_000,
            'actual_amount' => 1_500_000,
        ])
        ->assertSessionHas('success');

    $budget->refresh();

    expect($budget->period)->toBe('2026-08');
    expect((float) $budget->actual_amount)->toBe(1_500_000.0);
});

it('deletes a budget', function (): void {
    $budget = Budget::create([
        'tenant_id' => $this->tenant->id,
        'category' => 'other',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 100,
        'actual_amount' => 0,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.anggaran.destroy', $budget))
        ->assertSessionHas('success');

    expect(Budget::find($budget->id))->toBeNull();
});

it('returns 404 when deleting a budget from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = Budget::create([
        'tenant_id' => $otherTenant->id,
        'category' => 'other',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 100,
        'actual_amount' => 0,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.anggaran.destroy', $foreign))
        ->assertNotFound();

    expect(Budget::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from managing budgets', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.anggaran'))->assertForbidden();
    actingAs($staff)->post(route('avana.anggaran.store'), [
        'category' => 'operational',
        'period_type' => 'monthly',
        'period' => '2026-07',
        'planned_amount' => 1000,
        'actual_amount' => 0,
    ])->assertForbidden();
});
