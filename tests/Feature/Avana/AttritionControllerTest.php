<?php

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttritionScorer;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the attrition index with scored rows and KPIs', function (): void {
    actingAs($this->admin)
        ->get(route('avana.attrition'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/attrition/index', false)
            ->has('rows')
            ->has('kpis.total')
            ->has('kpis.high')
            ->has('kpis.medium')
            ->has('kpis.low')
            ->has('kpis.avg'));
});

it('renders an employee risk breakdown with all nine factors', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();

    actingAs($this->admin)
        ->get(route('avana.attrition.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/attrition/show', false)
            ->where('employee.name', $employee->full_name)
            ->has('result.score')
            ->has('result.category')
            ->has('result.factors', 9));
});

it('forbids a user without the attrition permission', function (): void {
    // Admin passes the Avana gate but the permission is stripped, so ensureCan
    // must reject the request.
    $permission = Permission::where('code', 'attrition.view')->firstOrFail();
    $this->admin->roles->each(fn ($role) => $role->permissions()->detach($permission->id));

    actingAs($this->admin)
        ->get(route('avana.attrition'))
        ->assertForbidden();
});

it('flags a short-tenure employee on the tenure factor', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();
    $employee->update(['join_date' => now()->subMonths(4)]);

    $result = app(AttritionScorer::class)->score($employee->fresh());
    $tenure = collect($result['factors'])->firstWhere('key', 'tenure');

    expect($tenure['available'])->toBeTrue()
        ->and($tenure['triggered'])->toBeTrue();
});

it('excludes a factor without data from the coverage denominator', function (): void {
    // The seeder skips attrition inputs under tests, so engagement has no data.
    $employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();

    $result = app(AttritionScorer::class)->score($employee);
    $engagement = collect($result['factors'])->firstWhere('key', 'engagement');

    expect($engagement['available'])->toBeFalse()
        ->and($result['coverage'])->toBeLessThan(100)
        ->and($result['score'])->toBeGreaterThanOrEqual(0)
        ->and($result['score'])->toBeLessThanOrEqual(100);
});
