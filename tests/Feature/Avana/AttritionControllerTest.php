<?php

use App\Models\AttritionSetting;
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

it('renders the settings page with factor weights and bands', function (): void {
    actingAs($this->admin)
        ->get(route('avana.attrition.settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/attrition/settings', false)
            ->has('factors', 9)
            ->has('bands.low')
            ->has('bands.medium')
            ->has('defaults.weights'));
});

it('saves tuned weights and risk bands', function (): void {
    $weights = collect(AttritionScorer::FACTOR_LABELS)->mapWithKeys(fn ($l, $k) => [$k => 10])->all();

    actingAs($this->admin)
        ->put(route('avana.attrition.settings.update'), [
            'weights' => $weights,
            'band_low' => 20,
            'band_medium' => 50,
        ])
        ->assertRedirect(route('avana.attrition'))
        ->assertSessionHas('success');

    $settings = AttritionSetting::forTenant($this->tenant->id)->firstOrFail();

    expect($settings->band_low)->toBe(20)
        ->and($settings->band_medium)->toBe(50)
        ->and($settings->weights['tenure'])->toBe(10);
});

it('rejects a medium band not greater than the low band', function (): void {
    actingAs($this->admin)
        ->put(route('avana.attrition.settings.update'), [
            'weights' => ['tenure' => 10],
            'band_low' => 50,
            'band_medium' => 40,
        ])
        ->assertSessionHasErrors('band_medium');
});

it('rejects an all-zero weight configuration', function (): void {
    $weights = collect(AttritionScorer::FACTOR_LABELS)->mapWithKeys(fn ($l, $k) => [$k => 0])->all();

    actingAs($this->admin)
        ->put(route('avana.attrition.settings.update'), [
            'weights' => $weights,
            'band_low' => 20,
            'band_medium' => 50,
        ])
        ->assertSessionHasErrors('weights');
});

it('applies the tenant tuned bands to the score category', function (): void {
    $weights = collect(AttritionScorer::FACTOR_LABELS)
        ->mapWithKeys(fn ($l, $k) => [$k => $k === 'tenure' ? 15 : 0])
        ->all();
    AttritionSetting::updateOrCreate(
        ['tenant_id' => $this->tenant->id],
        ['weights' => $weights, 'band_low' => 0, 'band_medium' => 1],
    );

    $employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();
    $employee->update(['join_date' => now()->subMonths(3)]);

    $result = app(AttritionScorer::class)->score($employee->fresh());

    expect($result['score'])->toBe(100)
        ->and($result['category'])->toBe('high');
});
