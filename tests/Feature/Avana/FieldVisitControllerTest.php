<?php

use App\Models\Employee;
use App\Models\FieldVisit;
use App\Models\FieldVisitPhoto;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FieldVisitPhotoStore;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create a field visit for the seeded tenant.
 */
function makeFieldVisit(int $tenantId, array $overrides = []): FieldVisit
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return FieldVisit::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'visit_date' => '2026-07-01',
        'location' => 'Gedung Sahid Jakarta',
        'client_name' => 'PT Klien Sejahtera',
        'purpose' => 'Presentasi produk',
        'notes' => 'Tindak lanjut minggu depan',
        'status' => 'submitted',
    ], $overrides));
}

it('renders the visiting index with visits and employees scoped to the tenant', function (): void {
    makeFieldVisit($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.visiting'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/visiting/index', false)
            ->has('visits.data')
            ->has('visits.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('visit_date')
                ->has('location')
                ->has('client_name')
                ->has('purpose')
                ->has('photo_urls')
                ->has('latitude')
                ->has('longitude')
                ->has('status')
                ->etc())
            ->has('employees')
            ->has('filters'));
});

it('only lists field visits that belong to the current tenant', function (): void {
    makeFieldVisit($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    makeFieldVisit($otherTenant->id, ['employee_id' => $foreignEmployee->id]);

    $tenantTotal = FieldVisit::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.visiting'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('visits.meta.total', $tenantTotal));
});

it('creates a field visit without a photo', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_id' => $employee->id,
            'visit_date' => '2026-07-10',
            'location' => 'Plaza Indonesia',
            'client_name' => 'PT Mitra Abadi',
            'purpose' => 'Survey lokasi',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
        ])
        ->assertRedirect(route('avana.visiting'))
        ->assertSessionHas('success');

    $visit = FieldVisit::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($visit->tenant_id)->toBe($this->tenant->id);
    expect($visit->location)->toBe('Plaza Indonesia');
    expect($visit->status)->toBe('submitted');
    expect($visit->photos)->toHaveCount(0);
});

it('stores every uploaded photo on the public disk', function (): void {
    Storage::fake('public');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_id' => $employee->id,
            'visit_date' => '2026-07-11',
            'location' => 'Kantor Klien BSD',
            'photos' => [
                UploadedFile::fake()->image('kunjungan-1.jpg'),
                UploadedFile::fake()->image('kunjungan-2.jpg'),
                UploadedFile::fake()->image('kunjungan-3.jpg'),
            ],
        ])
        ->assertRedirect(route('avana.visiting'))
        ->assertSessionHas('success');

    $visit = FieldVisit::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($visit->photos)->toHaveCount(3);

    foreach ($visit->photos as $photo) {
        Storage::disk('public')->assertExists($photo->file_path);
        expect($photo->tenant_id)->toBe($this->tenant->id)
            ->and($photo->employee_id)->toBe($employee->id);
    }
});

it('rejects more photos than a visit may carry', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_id' => $employee->id,
            'visit_date' => '2026-07-11',
            'location' => 'Kantor Klien BSD',
            'photos' => array_map(
                fn (int $i) => UploadedFile::fake()->image("foto-{$i}.jpg"),
                range(1, FieldVisitPhotoStore::MAX + 1),
            ),
        ])
        ->assertSessionHasErrors('photos');
});

it('rejects a non-image among the photos', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_id' => $employee->id,
            'visit_date' => '2026-07-11',
            'location' => 'Kantor Klien BSD',
            'photos' => [
                UploadedFile::fake()->image('ok.jpg'),
                UploadedFile::fake()->create('virus.pdf', 100),
            ],
        ])
        ->assertSessionHasErrors('photos.1');
});

it('deletes the photo files when the visit is deleted', function (): void {
    Storage::fake('public');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)->post(route('avana.visiting.store'), [
        'employee_id' => $employee->id,
        'visit_date' => '2026-07-11',
        'location' => 'Kantor Klien BSD',
        'photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ],
    ]);

    $visit = FieldVisit::where('employee_id', $employee->id)->latest('id')->firstOrFail();
    $paths = $visit->photos->pluck('file_path')->all();

    actingAs($this->admin)->delete(route('avana.visiting.destroy', $visit))
        ->assertRedirect();

    foreach ($paths as $path) {
        Storage::disk('public')->assertMissing($path);
    }

    expect(FieldVisitPhoto::where('field_visit_id', $visit->id)->count())->toBe(0);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_id' => '',
            'visit_date' => '',
            'location' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'visit_date', 'location']);
});

it('deletes a field visit', function (): void {
    $visit = makeFieldVisit($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.visiting.destroy', $visit))
        ->assertSessionHas('success');

    expect(FieldVisit::find($visit->id))->toBeNull();
});

it('returns 404 when deleting a field visit from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = makeFieldVisit($otherTenant->id, ['employee_id' => $foreignEmployee->id]);

    actingAs($this->admin)
        ->delete(route('avana.visiting.destroy', $foreign))
        ->assertNotFound();

    expect(FieldVisit::find($foreign->id))->not->toBeNull();
});

it('forbids users without attendance permissions from listing field visits', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.visiting'))
        ->assertForbidden();
});
