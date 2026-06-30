<?php

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();
});

/**
 * Create an employee document for the demo tenant's first employee.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeDocument(array $attributes = []): EmployeeDocument
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;
    /** @var Employee $employee */
    $employee = test()->employee;

    return EmployeeDocument::create(array_merge([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'name' => 'KTP '.fake()->firstName(),
        'type' => 'KTP',
        'file_path' => "documents/{$tenant->id}/ktp.pdf",
        'file_size' => 102400,
        'uploaded_at' => now(),
    ], $attributes));
}

it('renders the dokumen index with the expected props', function (): void {
    makeDocument();

    actingAs($this->admin)
        ->get(route('avana.dokumen'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/dokumen/index', false)
            ->has('documents.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee_id')
                ->has('employee')
                ->has('name')
                ->has('type')
                ->has('file_size')
                ->has('file_size_label')
                ->has('uploaded_at')
                ->has('download_url'))
            ->has('employees.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name'))
            ->has('kpis'));
});

it('only lists documents that belong to the current tenant', function (): void {
    makeDocument();

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    EmployeeDocument::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'name' => 'Dokumen Asing',
        'type' => 'KTP',
        'file_path' => "documents/{$otherTenant->id}/asing.pdf",
        'file_size' => 2048,
        'uploaded_at' => now(),
    ]);

    $tenantTotal = EmployeeDocument::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.dokumen'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents', $tenantTotal));
});

it('uploads a document scoped to the current tenant', function (): void {
    Storage::fake('public');

    actingAs($this->admin)
        ->post(route('avana.dokumen.store'), [
            'employee_id' => $this->employee->id,
            'name' => 'KTP Budi',
            'type' => 'KTP',
            'file' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $document = EmployeeDocument::where('name', 'KTP Budi')->firstOrFail();

    expect($document->tenant_id)->toBe($this->tenant->id);
    expect($document->employee_id)->toBe($this->employee->id);
    expect($document->type)->toBe('KTP');
    expect($document->file_size)->not->toBeNull();
    expect($document->file_path)->not->toBeNull();
    Storage::disk('public')->assertExists($document->file_path);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.dokumen.store'), [
            'employee_id' => 99999,
            'name' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'name', 'file']);
});

it('deletes a document and removes its stored file', function (): void {
    Storage::fake('public');

    $path = UploadedFile::fake()
        ->create('ijazah.pdf', 100, 'application/pdf')
        ->store("documents/{$this->tenant->id}", 'public');

    $document = makeDocument(['file_path' => $path]);

    Storage::disk('public')->assertExists($path);

    actingAs($this->admin)
        ->delete(route('avana.dokumen.destroy', $document))
        ->assertSessionHas('success');

    expect(EmployeeDocument::find($document->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('returns 404 when deleting a document from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-8888',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = EmployeeDocument::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'name' => 'Dokumen Asing',
        'type' => 'KTP',
        'file_path' => "documents/{$otherTenant->id}/asing.pdf",
        'file_size' => 2048,
        'uploaded_at' => now(),
    ]);

    actingAs($this->admin)
        ->delete(route('avana.dokumen.destroy', $foreign))
        ->assertNotFound();

    expect(EmployeeDocument::find($foreign->id))->not->toBeNull();
});

it('forbids a plain employee from listing or uploading documents', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.dokumen'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.dokumen.store'), [
            'employee_id' => $this->employee->id,
            'name' => 'Tidak Boleh',
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();
});
