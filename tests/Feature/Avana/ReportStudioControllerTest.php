<?php

use App\Models\CustomField;
use App\Models\Employee;
use App\Models\ReportStudioReport;
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

it('exports the current pivot as an Excel download', function (): void {
    $payload = json_encode([
        'rows' => ['department'],
        'columns' => [],
        'values' => [['field' => 'count', 'agg' => 'sum']],
    ]);

    $response = actingAs($this->admin)
        ->get(route('avana.report-studio.export', ['payload' => $payload]))
        ->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('shows the manage-fields affordance to an admin', function (): void {
    actingAs($this->admin)
        ->get(route('avana.report-studio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManageFields', true));
});

it('a field added via the custom-field store shows up in the palette', function (): void {
    // Mirrors the in-page "Tambah Field" modal, which posts to custom-fields.
    actingAs($this->admin)
        ->post(route('avana.custom-fields.store'), ['label' => 'Skor Loyalitas', 'type' => 'number'])
        ->assertRedirect();

    actingAs($this->admin)
        ->get(route('avana.report-studio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('measures', fn ($measures) => collect($measures)->contains('key', 'cf_skor_loyalitas')));
});

it('surfaces tenant custom fields in the palette', function (): void {
    CustomField::create(['tenant_id' => $this->tenant->id, 'entity' => 'employee', 'key' => 'golongan_darah', 'label' => 'Golongan Darah', 'type' => 'select', 'options' => ['A', 'B', 'AB', 'O'], 'status' => 'active']);
    CustomField::create(['tenant_id' => $this->tenant->id, 'entity' => 'employee', 'key' => 'jumlah_tanggungan', 'label' => 'Jumlah Tanggungan', 'type' => 'number', 'status' => 'active']);

    actingAs($this->admin)
        ->get(route('avana.report-studio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dimensions', fn ($dims) => collect($dims)->contains('key', 'cf_golongan_darah'))
            ->where('measures', fn ($measures) => collect($measures)->contains('key', 'cf_jumlah_tanggungan')));
});

it('aggregates a number custom field and groups by a select one', function (): void {
    CustomField::create(['tenant_id' => $this->tenant->id, 'entity' => 'employee', 'key' => 'jumlah_tanggungan', 'label' => 'Jumlah Tanggungan', 'type' => 'number', 'status' => 'active']);

    $employees = Employee::forTenant($this->tenant->id)->where('status', 'active')->take(2)->get();
    $employees[0]->update(['custom_data' => ['jumlah_tanggungan' => 3]]);
    $employees[1]->update(['custom_data' => ['jumlah_tanggungan' => 1]]);

    $response = actingAs($this->admin)
        ->postJson(route('avana.report-studio.run'), [
            'payload' => json_encode([
                'rows' => ['department'],
                'columns' => [],
                'values' => [['field' => 'cf_jumlah_tanggungan', 'agg' => 'sum']],
            ]),
        ])
        ->assertOk();

    $total = collect($response->json('rows'))->sum(fn (array $row): float => (float) ($row['cells'][0] ?? 0));
    expect($total)->toBe(4.0);
});

it('ignores a custom field key that is not defined for the tenant', function (): void {
    $response = actingAs($this->admin)
        ->postJson(route('avana.report-studio.run'), [
            'payload' => json_encode([
                'rows' => ['department'],
                'columns' => [],
                'values' => [['field' => 'cf_tidak_ada', 'agg' => 'sum'], ['field' => 'count', 'agg' => 'sum']],
            ]),
        ])
        ->assertOk();

    // Only the valid measure survives; the undefined custom key is dropped.
    expect($response->json('columns'))->toHaveCount(1);
});

it('lets a user save a report and lists it back', function (): void {
    actingAs($this->admin)
        ->post(route('avana.report-studio.store'), [
            'name' => 'Headcount Divisi',
            'payload' => json_encode([
                'rows' => ['department'],
                'columns' => [],
                'values' => [['field' => 'count', 'agg' => 'sum']],
            ]),
        ])
        ->assertRedirect();

    $saved = ReportStudioReport::forTenant($this->tenant->id)->first();
    expect($saved)->not->toBeNull()
        ->and($saved->name)->toBe('Headcount Divisi')
        ->and($saved->config['rows'])->toBe(['department']);

    actingAs($this->admin)
        ->get(route('avana.report-studio'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('savedReports', 1));
});

it('rejects saving a report with no rows or values', function (): void {
    actingAs($this->admin)
        ->post(route('avana.report-studio.store'), [
            'name' => 'Kosong',
            'payload' => json_encode(['rows' => [], 'columns' => [], 'values' => []]),
        ])
        ->assertSessionHasErrors('name');

    expect(ReportStudioReport::forTenant($this->tenant->id)->count())->toBe(0);
});

it('deletes a saved report', function (): void {
    $report = ReportStudioReport::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Uji',
        'config' => ['rows' => ['department'], 'columns' => [], 'values' => [['field' => 'count', 'agg' => 'sum']]],
        'created_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.report-studio.destroy', $report))
        ->assertRedirect();

    expect(ReportStudioReport::find($report->id))->toBeNull();
});

it('cannot delete another tenant saved report', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $report = ReportStudioReport::create([
        'tenant_id' => $other->id,
        'name' => 'Milik lain',
        'config' => ['rows' => ['department'], 'columns' => [], 'values' => [['field' => 'count', 'agg' => 'sum']]],
    ]);

    actingAs($this->admin)
        ->delete(route('avana.report-studio.destroy', $report))
        ->assertNotFound();

    expect(ReportStudioReport::find($report->id))->not->toBeNull();
});

it('forbids a user without the report permission', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)->get(route('avana.report-studio'))->assertForbidden();
    actingAs($staff)->postJson(route('avana.report-studio.run'), [])->assertForbidden();
    actingAs($staff)->post(route('avana.report-studio.store'), ['name' => 'x'])->assertForbidden();
});
