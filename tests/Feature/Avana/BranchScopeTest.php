<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveRequest;
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
    $this->hrRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'admin_tenant_hr')->firstOrFail();
    $this->employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
});

it('persists the data scope and branch access on store', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.pengguna.store'), [
            'name' => 'Cabang Manager',
            'email' => 'cabang@pengguna.test',
            'password' => 'rahasia123',
            'status' => 'active',
            'role_ids' => [$this->hrRole->id],
            'data_scope' => 'branch',
            'branch_ids' => [$branch->id],
        ])
        ->assertRedirect(route('avana.pengguna'))
        ->assertSessionHas('success');

    $user = User::where('email', 'cabang@pengguna.test')->firstOrFail();

    expect($user->dataScopes()->where('scope_type', 'branch')->exists())->toBeTrue();
    expect($user->branchAccesses()->where('branch_id', $branch->id)->where('access_type', 'view')->exists())->toBeTrue();
});

it('replaces the data scope and branch access on update', function (): void {
    $branchA = Branch::forTenant($this->tenant->id)->orderBy('id')->first();
    $branchB = Branch::forTenant($this->tenant->id)->orderByDesc('id')->first();

    $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $target->dataScopes()->create(['scope_type' => 'branch', 'scope_value' => null]);
    $target->branchAccesses()->create(['branch_id' => $branchA->id, 'access_type' => 'view']);

    actingAs($this->admin)
        ->put(route('avana.pengguna.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'status' => 'active',
            'role_ids' => [$this->hrRole->id],
            'data_scope' => 'branch',
            'branch_ids' => [$branchB->id],
        ])
        ->assertRedirect(route('avana.pengguna'))
        ->assertSessionHas('success');

    expect($target->dataScopes()->count())->toBe(1);
    expect($target->branchAccesses()->pluck('branch_id')->all())->toBe([$branchB->id]);
});

it('rejects a branch id from another tenant on store', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-scope']);
    $foreignBranch = Branch::create(['tenant_id' => $otherTenant->id, 'code' => 'XXX', 'name' => 'Asing', 'status' => 'active']);

    actingAs($this->admin)
        ->post(route('avana.pengguna.store'), [
            'name' => 'Invalid Branch',
            'email' => 'invalid@pengguna.test',
            'password' => 'rahasia123',
            'status' => 'active',
            'data_scope' => 'branch',
            'branch_ids' => [$foreignBranch->id],
        ])
        ->assertSessionHasErrors(['branch_ids.0']);
});

it('exposes branches and per-row scope metadata on the index', function (): void {
    actingAs($this->admin)
        ->get(route('avana.pengguna'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches')
            ->has('users.data.0', fn (Assert $row) => $row
                ->has('data_scope')
                ->has('branch_ids')
                ->etc()));
});

it('limits the employee list to the assigned branch for a branch-scoped user', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->where('code', 'JKT')->firstOrFail();

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->roles()->sync([$this->hrRole->id]);
    $user->dataScopes()->create(['scope_type' => 'branch', 'scope_value' => null]);
    $user->branchAccesses()->create(['branch_id' => $branch->id, 'access_type' => 'view']);

    $branchTotal = Employee::forTenant($this->tenant->id)->where('branch_id', $branch->id)->count();
    $tenantTotal = Employee::forTenant($this->tenant->id)->count();

    expect($branchTotal)->toBeGreaterThan(0);
    expect($branchTotal)->toBeLessThan($tenantTotal);

    actingAs($user)
        ->get(route('avana.employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('employees.meta.total', $branchTotal)
            ->where('employees.data', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['branch']['id'] === $branch->id,
            )));
});

it('matches no employees when a branch-scoped user has no assigned branch', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->roles()->sync([$this->hrRole->id]);
    $user->dataScopes()->create(['scope_type' => 'branch', 'scope_value' => null]);

    actingAs($user)
        ->get(route('avana.employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('employees.meta.total', 0));
});

it('limits leave requests to the assigned branch for a branch-scoped user', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->where('code', 'JKT')->firstOrFail();

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->roles()->sync([$this->hrRole->id]);
    $user->dataScopes()->create(['scope_type' => 'branch', 'scope_value' => null]);
    $user->branchAccesses()->create(['branch_id' => $branch->id, 'access_type' => 'view']);

    $branchTotal = LeaveRequest::forTenant($this->tenant->id)->where('branch_id', $branch->id)->count();
    $tenantTotal = LeaveRequest::forTenant($this->tenant->id)->count();

    expect($branchTotal)->toBeLessThan($tenantTotal);

    actingAs($user)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', $branchTotal));
});

it('still shows all tenant employees for an unscoped admin (regression guard)', function (): void {
    $tenantTotal = Employee::forTenant($this->tenant->id)->count();

    expect($this->admin->dataScopes()->count())->toBe(0);

    actingAs($this->admin)
        ->get(route('avana.employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('employees.meta.total', $tenantTotal));
});
