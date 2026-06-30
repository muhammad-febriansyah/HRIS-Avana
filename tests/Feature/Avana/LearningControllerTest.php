<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Training;
use App\Models\TrainingEnrollment;
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
 * Create a training for the given tenant.
 */
function makeTraining(int $tenantId, array $overrides = []): Training
{
    return Training::factory()->create(array_merge([
        'tenant_id' => $tenantId,
    ], $overrides));
}

/**
 * Create an enrollment linking a tenant employee to a tenant training.
 */
function makeEnrollment(int $tenantId, array $overrides = []): TrainingEnrollment
{
    $training = $overrides['training_id'] ?? makeTraining($tenantId)->id;
    $employee = $overrides['employee_id'] ?? Employee::forTenant($tenantId)->firstOrFail()->id;

    return TrainingEnrollment::factory()->create(array_merge([
        'tenant_id' => $tenantId,
        'training_id' => $training,
        'employee_id' => $employee,
    ], $overrides));
}

it('renders the learning index with the expected props', function (): void {
    makeTraining($this->tenant->id);
    makeEnrollment($this->tenant->id, ['status' => 'completed']);

    actingAs($this->admin)
        ->get(route('avana.pembelajaran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/pembelajaran/index', false)
            ->has('trainings.0', fn (Assert $row) => $row
                ->has('id')
                ->has('title')
                ->has('category')
                ->has('type')
                ->has('start_date')
                ->has('end_date')
                ->has('cost')
                ->has('instructor')
                ->has('quota')
                ->has('status')
                ->has('description')
                ->has('enrollments_count'))
            ->has('enrollments.0', fn (Assert $row) => $row
                ->has('id')
                ->has('training_id')
                ->has('training_title')
                ->has('employee')
                ->has('status')
                ->has('score')
                ->has('certificate_no')
                ->has('completed_date'))
            ->has('employees')
            ->has('statuses')
            ->has('kpis'));
});

it('only lists trainings that belong to the current tenant', function (): void {
    makeTraining($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeTraining($otherTenant->id);

    $tenantTotal = Training::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.pembelajaran'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('trainings', $tenantTotal));
});

it('creates a training scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pembelajaran.store'), [
            'title' => 'Pelatihan K3 Dasar',
            'category' => 'Kepatuhan',
            'type' => 'internal',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-03',
            'cost' => 1_500_000,
            'instructor' => 'Pak Budi',
            'quota' => 20,
            'status' => 'planned',
            'description' => 'Materi keselamatan kerja',
        ])
        ->assertRedirect(route('avana.pembelajaran'))
        ->assertSessionHas('success');

    $training = Training::where('title', 'Pelatihan K3 Dasar')->firstOrFail();

    expect($training->tenant_id)->toBe($this->tenant->id);
    expect($training->type)->toBe('internal');
    expect($training->quota)->toBe(20);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pembelajaran.store'), [
            'title' => '',
            'category' => '',
            'type' => 'invalid',
            'cost' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['title', 'category', 'type', 'cost', 'status']);
});

it('updates an existing training', function (): void {
    $training = makeTraining($this->tenant->id, ['title' => 'Judul Lama']);

    actingAs($this->admin)
        ->put(route('avana.pembelajaran.update', $training), [
            'title' => 'Judul Baru',
            'category' => 'Teknis',
            'type' => 'online',
            'start_date' => null,
            'end_date' => null,
            'cost' => 0,
            'instructor' => null,
            'quota' => null,
            'status' => 'ongoing',
            'description' => null,
        ])
        ->assertRedirect(route('avana.pembelajaran'))
        ->assertSessionHas('success');

    $training->refresh();

    expect($training->title)->toBe('Judul Baru');
    expect($training->type)->toBe('online');
    expect($training->status)->toBe('ongoing');
});

it('deletes a training', function (): void {
    $training = makeTraining($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.pembelajaran.destroy', $training))
        ->assertSessionHas('success');

    expect(Training::find($training->id))->toBeNull();
});

it('enrolls an employee into a training', function (): void {
    $training = makeTraining($this->tenant->id);
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.pembelajaran.enroll'), [
            'training_id' => $training->id,
            'employee_id' => $employee->id,
            'status' => 'enrolled',
        ])
        ->assertRedirect(route('avana.pembelajaran'))
        ->assertSessionHas('success');

    $enrollment = TrainingEnrollment::where('training_id', $training->id)
        ->where('employee_id', $employee->id)
        ->firstOrFail();

    expect($enrollment->tenant_id)->toBe($this->tenant->id);
    expect($enrollment->status)->toBe('enrolled');
});

it('validates the enroll request against tenant-scoped records', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pembelajaran.enroll'), [
            'training_id' => 99999,
            'employee_id' => 99999,
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['training_id', 'employee_id', 'status']);
});

it('updates an enrollment progress and certificate', function (): void {
    $enrollment = makeEnrollment($this->tenant->id);

    actingAs($this->admin)
        ->put(route('avana.pembelajaran.enroll.update', $enrollment), [
            'status' => 'completed',
            'score' => 88.5,
            'certificate_no' => 'CERT-9999',
            'completed_date' => '2026-07-10',
        ])
        ->assertSessionHas('success');

    $enrollment->refresh();

    expect($enrollment->status)->toBe('completed');
    expect((float) $enrollment->score)->toBe(88.5);
    expect($enrollment->certificate_no)->toBe('CERT-9999');
    expect($enrollment->completed_date->toDateString())->toBe('2026-07-10');
});

it('returns 404 when updating a training from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeTraining($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.pembelajaran.update', $foreign), [
            'title' => 'Hack',
            'category' => 'Teknis',
            'type' => 'internal',
            'cost' => 0,
            'status' => 'planned',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a training from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeTraining($otherTenant->id);

    actingAs($this->admin)
        ->delete(route('avana.pembelajaran.destroy', $foreign))
        ->assertNotFound();

    expect(Training::find($foreign->id))->not->toBeNull();
});

it('returns 404 when updating an enrollment from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignTraining = makeTraining($otherTenant->id);
    $foreign = TrainingEnrollment::factory()->create([
        'tenant_id' => $otherTenant->id,
        'training_id' => $foreignTraining->id,
        'employee_id' => Employee::query()->firstOrFail()->id,
    ]);

    actingAs($this->admin)
        ->put(route('avana.pembelajaran.enroll.update', $foreign), [
            'status' => 'completed',
        ])
        ->assertNotFound();

    expect($foreign->fresh()->status)->toBe('enrolled');
});

it('forbids a plain employee from listing or creating trainings', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.pembelajaran'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.pembelajaran.store'), [
            'title' => 'Tidak Boleh',
            'category' => 'Teknis',
            'type' => 'internal',
            'cost' => 0,
            'status' => 'planned',
        ])
        ->assertForbidden();
});
