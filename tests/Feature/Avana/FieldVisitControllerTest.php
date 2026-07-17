<?php

use App\Models\Branch;
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
 * Create a field visit for the seeded tenant, with its owner on the pivot.
 */
function makeFieldVisit(int $tenantId, array $overrides = []): FieldVisit
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    $visit = FieldVisit::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'visit_date' => '2026-07-01',
        'location' => 'Gedung Sahid Jakarta',
        'client_name' => 'PT Klien Sejahtera',
        'purpose' => 'Presentasi produk',
        'notes' => 'Tindak lanjut minggu depan',
        'status' => 'submitted',
    ], $overrides));

    $visit->syncAttendees([]);

    return $visit;
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
                ->has('employees.0.name')
                ->has('employees.0.initials')
                ->has('employees.0.avatar_color')
                ->has('branch')
                ->has('visit_date')
                ->has('location')
                ->has('client_name')
                ->has('purpose')
                ->has('photo_urls')
                ->has('latitude')
                ->has('longitude')
                ->has('status')
                ->has('tasks')
                ->has('task_progress.done')
                ->has('task_progress.total')
                ->etc())
            ->has('employees')
            ->has('branches')
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
            'employee_ids' => [$employee->id],
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

it('records every selected employee as an attendee', function (): void {
    $employees = Employee::forTenant($this->tenant->id)->orderBy('id')->take(3)->get();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_ids' => $employees->pluck('id')->all(),
            'visit_date' => '2026-07-10',
            'location' => 'Plaza Indonesia',
        ])
        ->assertSessionHas('success');

    $visit = FieldVisit::latest('id')->firstOrFail();

    expect($visit->employees->pluck('id')->sort()->values()->all())
        ->toBe($employees->pluck('id')->sort()->values()->all());

    // The first pick owns the report; the ESS app and the photos hang off it.
    expect($visit->employee_id)->toBe($employees->first()->id);
});

it('keeps the filing employee on the attendee list even when not passed again', function (): void {
    $visit = makeFieldVisit($this->tenant->id);
    $other = Employee::forTenant($this->tenant->id)->where('id', '!=', $visit->employee_id)->firstOrFail();

    $visit->syncAttendees([$other->id]);

    expect($visit->employees()->pluck('employees.id')->all())
        ->toContain($visit->employee_id)
        ->toContain($other->id);
});

it('rejects an employee from another tenant among the attendees', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-visit']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-5555',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_ids' => [$employee->id, $foreignEmployee->id],
            'visit_date' => '2026-07-10',
            'location' => 'Plaza Indonesia',
        ])
        ->assertSessionHasErrors(['employee_ids.1']);
});

it('records the branch a visit was reported against', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $branch = Branch::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_ids' => [$employee->id],
            'branch_id' => $branch->id,
            'visit_date' => '2026-07-10',
            'location' => 'Plaza Indonesia',
        ])
        ->assertSessionHas('success');

    expect(FieldVisit::latest('id')->firstOrFail()->branch_id)->toBe($branch->id);
});

it('rejects a branch from another tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-branch']);
    $foreignBranch = Branch::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Cabang Asing',
        'code' => 'ASG-01',
    ]);

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_ids' => [$employee->id],
            'branch_id' => $foreignBranch->id,
            'visit_date' => '2026-07-10',
            'location' => 'Plaza Indonesia',
        ])
        ->assertSessionHasErrors(['branch_id']);
});

it('filters the list by branch', function (): void {
    $branch = Branch::forTenant($this->tenant->id)->firstOrFail();
    makeFieldVisit($this->tenant->id, ['branch_id' => $branch->id]);
    makeFieldVisit($this->tenant->id, ['branch_id' => null]);

    actingAs($this->admin)
        ->get(route('avana.visiting', ['branch_id' => $branch->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('visits.meta.total', 1));
});

it('saves the tasklist in the order it was entered', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_ids' => [$employee->id],
            'visit_date' => '2026-07-10',
            'location' => 'Plaza Indonesia',
            'tasks' => [
                'Cek ketersediaan stok barang',
                'Wawancara feedback pelanggan',
                'Dokumentasi visual area toko',
            ],
        ])
        ->assertSessionHas('success');

    $visit = FieldVisit::latest('id')->firstOrFail();

    expect($visit->tasks->pluck('title')->all())->toBe([
        'Cek ketersediaan stok barang',
        'Wawancara feedback pelanggan',
        'Dokumentasi visual area toko',
    ]);
    expect($visit->tasks->pluck('is_done')->all())->toBe([false, false, false]);
    expect($visit->taskProgress())->toBe(['done' => 0, 'total' => 3]);
});

it('ticks a task off and back on, moving the progress with it', function (): void {
    $visit = makeFieldVisit($this->tenant->id);
    $task = $visit->tasks()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Cek stok',
        'sort_order' => 0,
    ]);

    actingAs($this->admin)
        ->post(route('avana.visiting.tasks.toggle', ['visit' => $visit, 'task' => $task]))
        ->assertSessionHas('success');

    $task->refresh();
    expect($task->is_done)->toBeTrue();
    expect($task->done_at)->not->toBeNull();
    expect($visit->fresh()->taskProgress())->toBe(['done' => 1, 'total' => 1]);

    actingAs($this->admin)
        ->post(route('avana.visiting.tasks.toggle', ['visit' => $visit, 'task' => $task]))
        ->assertSessionHas('success');

    $task->refresh();
    expect($task->is_done)->toBeFalse();
    expect($task->done_at)->toBeNull();
    expect($visit->fresh()->taskProgress())->toBe(['done' => 0, 'total' => 1]);
});

it('returns 404 when toggling a task that belongs to another visit', function (): void {
    $visit = makeFieldVisit($this->tenant->id);
    $otherVisit = makeFieldVisit($this->tenant->id);
    $task = $otherVisit->tasks()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Tugas lain',
        'sort_order' => 0,
    ]);

    actingAs($this->admin)
        ->post(route('avana.visiting.tasks.toggle', ['visit' => $visit, 'task' => $task]))
        ->assertNotFound();

    expect($task->fresh()->is_done)->toBeFalse();
});

it('stores every uploaded photo on the public disk', function (): void {
    Storage::fake('public');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.visiting.store'), [
            'employee_ids' => [$employee->id],
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
            'employee_ids' => [$employee->id],
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
            'employee_ids' => [$employee->id],
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
        'employee_ids' => [$employee->id],
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
            'employee_ids' => [],
            'visit_date' => '',
            'location' => '',
        ])
        ->assertSessionHasErrors(['employee_ids', 'visit_date', 'location']);
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
