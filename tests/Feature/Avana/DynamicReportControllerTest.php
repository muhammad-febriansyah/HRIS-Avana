<?php

use App\Models\Role;
use App\Models\SavedReport;
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
 * Create a saved report definition for the given tenant.
 */
function makeSavedReport(int $tenantId, array $overrides = []): SavedReport
{
    return SavedReport::factory()->create(array_merge([
        'tenant_id' => $tenantId,
    ], $overrides));
}

it('renders the dynamic report index with saved reports and entity metadata', function (): void {
    makeSavedReport($this->tenant->id, ['name' => 'Karyawan Aktif']);

    actingAs($this->admin)
        ->get(route('avana.dynamic-report'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/dynamic-report/index', false)
            ->has('reports.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('entity')
                ->has('entity_label')
                ->has('columns')
                ->has('column_labels')
                ->has('filters')
                ->has('created_at'))
            ->has('entities.0', fn (Assert $entity) => $entity
                ->has('key')
                ->has('label')
                ->has('columns')
                ->has('filters')));
});

it('stores a report definition scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.dynamic-report.store'), [
            'name' => 'Karyawan Tetap',
            'entity' => 'employees',
            'columns' => ['full_name', 'email', 'status', 'join_date'],
            'filters' => ['status' => 'active'],
        ])
        ->assertRedirect(route('avana.dynamic-report'))
        ->assertSessionHas('success');

    $report = SavedReport::where('name', 'Karyawan Tetap')->firstOrFail();

    expect($report->tenant_id)->toBe($this->tenant->id);
    expect($report->entity)->toBe('employees');
    expect($report->columns)->toBe(['full_name', 'email', 'status', 'join_date']);
    expect($report->filters)->toBe(['status' => 'active']);
    expect($report->created_by)->toBe($this->admin->id);
});

it('strips non-allowlisted columns when storing a report', function (): void {
    actingAs($this->admin)
        ->post(route('avana.dynamic-report.store'), [
            'name' => 'Coba Injeksi',
            'entity' => 'employees',
            'columns' => ['full_name', 'password', 'secret_salary'],
        ])
        ->assertRedirect(route('avana.dynamic-report'));

    $report = SavedReport::where('name', 'Coba Injeksi')->firstOrFail();

    expect($report->columns)->toBe(['full_name']);
});

it('rejects an unknown entity that is not on the allowlist', function (): void {
    actingAs($this->admin)
        ->post(route('avana.dynamic-report.store'), [
            'name' => 'Bocor Users',
            'entity' => 'users',
            'columns' => ['email'],
        ])
        ->assertSessionHasErrors('entity');

    expect(SavedReport::where('name', 'Bocor Users')->exists())->toBeFalse();
});

it('runs a saved report and renders the preview dataset', function (): void {
    $report = makeSavedReport($this->tenant->id, [
        'entity' => 'employees',
        'columns' => ['full_name', 'email', 'status'],
    ]);

    actingAs($this->admin)
        ->get(route('avana.dynamic-report.run', $report))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/dynamic-report/show', false)
            ->has('report')
            ->has('headers')
            ->has('rows')
            ->has('count')
            ->where('headers', ['Nama', 'Email', 'Status']));
});

it('exports a saved report as a streamed CSV', function (): void {
    $report = makeSavedReport($this->tenant->id, [
        'entity' => 'employees',
        'columns' => ['full_name', 'email'],
    ]);

    $response = actingAs($this->admin)->get(route('avana.dynamic-report.export', $report));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $body = $response->streamedContent();

    expect($body)->toContain('Nama', 'Email');
    expect($body)->toContain('Putri Anjani');
});

it('deletes a saved report', function (): void {
    $report = makeSavedReport($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.dynamic-report.destroy', $report))
        ->assertSessionHas('success');

    expect(SavedReport::find($report->id))->toBeNull();
});

it('returns 404 when running a report from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSavedReport($otherTenant->id);

    actingAs($this->admin)
        ->get(route('avana.dynamic-report.run', $foreign))
        ->assertNotFound();
});

it('returns 404 when exporting a report from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSavedReport($otherTenant->id);

    actingAs($this->admin)
        ->get(route('avana.dynamic-report.export', $foreign))
        ->assertNotFound();
});

it('returns 404 when deleting a report from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSavedReport($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.dynamic-report.destroy', $foreign))
        ->assertNotFound();

    expect(SavedReport::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from the dynamic report builder', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.dynamic-report'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.dynamic-report.store'), [
            'name' => 'Tidak Boleh',
            'entity' => 'employees',
            'columns' => ['full_name'],
        ])
        ->assertForbidden();
});
