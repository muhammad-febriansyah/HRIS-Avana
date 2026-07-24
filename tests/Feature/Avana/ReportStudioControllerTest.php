<?php

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

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the builder with the field palette and templates', function (): void {
    actingAs($this->admin)
        ->get(route('avana.report-studio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/report-studio/index', false)
            ->has('dimensions')
            ->has('measures')
            ->has('templates', 4));
});

it('pivots headcount per department to the active workforce', function (): void {
    $expected = Employee::forTenant($this->tenant->id)->where('status', 'active')->count();

    $response = actingAs($this->admin)
        ->postJson(route('avana.report-studio.run'), [
            'payload' => json_encode([
                'rows' => ['department'],
                'columns' => [],
                'values' => [['field' => 'count', 'agg' => 'sum']],
            ]),
        ])
        ->assertOk()
        ->assertJsonStructure(['columns', 'rows', 'chart', 'meta']);

    $total = collect($response->json('rows'))->sum(fn (array $row): int => (int) $row['cells'][0]);

    expect($total)->toBe($expected)
        ->and($response->json('columns.0.format'))->toBe('integer');
});

it('reports average basic salary per department as currency', function (): void {
    $response = actingAs($this->admin)
        ->postJson(route('avana.report-studio.run'), [
            'payload' => json_encode([
                'rows' => ['department'],
                'columns' => [],
                'values' => [['field' => 'gaji_pokok', 'agg' => 'avg']],
            ]),
        ])
        ->assertOk();

    expect($response->json('columns.0.format'))->toBe('currency')
        ->and($response->json('rows'))->not->toBeEmpty();
});

it('cross-tabs turnover by branch and employment status', function (): void {
    $response = actingAs($this->admin)
        ->postJson(route('avana.report-studio.run'), [
            'payload' => json_encode([
                'rows' => ['branch'],
                'columns' => ['employment_status'],
                'values' => [['field' => 'resign', 'agg' => 'sum']],
            ]),
        ])
        ->assertOk();

    expect($response->json('columns'))->not->toBeEmpty();
});

it('ignores fields outside the allowlist', function (): void {
    $response = actingAs($this->admin)
        ->postJson(route('avana.report-studio.run'), [
            'payload' => json_encode([
                'rows' => ['department', 'salary; DROP TABLE employees'],
                'columns' => [],
                'values' => [['field' => 'count', 'agg' => 'sum'], ['field' => 'hacker', 'agg' => 'sum']],
            ]),
        ])
        ->assertOk();

    // Only the valid row field + valid value survive sanitising.
    expect($response->json('meta.row_fields'))->toBe(['Departemen'])
        ->and($response->json('columns'))->toHaveCount(1);
});

it('exports the current pivot as a CSV download', function (): void {
    $response = actingAs($this->admin)
        ->post(route('avana.report-studio.export'), [
            'payload' => json_encode([
                'rows' => ['department'],
                'columns' => [],
                'values' => [['field' => 'count', 'agg' => 'sum']],
            ]),
        ])
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('forbids a user without the report permission', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.report-studio'))->assertForbidden();
    actingAs($staff)->postJson(route('avana.report-studio.run'), [])->assertForbidden();
});
