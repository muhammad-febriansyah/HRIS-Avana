<?php

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the attendance policy screen for an HR admin', function (): void {
    actingAs($this->admin)->get('/avana/absensi/kebijakan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi-kebijakan/index')
            ->has('policy')
            ->where('policy.face_enforcement', 'block'));
});

it('persists policy changes', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'attendance_scope' => 'assigned',
        'require_face_enrollment' => true,
        'require_liveness_challenge' => true,
        'face_enforcement' => 'flag',
        'integrity_enforcement' => 'block',
        'block_mock_location' => true,
        'block_rooted' => true,
        'block_emulator' => false,
    ])->assertRedirect();

    $policy = AttendancePolicy::where('tenant_id', $this->admin->tenant_id)->firstOrFail();
    expect($policy->require_face_enrollment)->toBeTrue();
    expect($policy->face_enforcement)->toBe('flag');
    expect($policy->block_emulator)->toBeFalse();
});

it('persists the attendance scope', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'attendance_scope' => 'anywhere',
        'face_enforcement' => 'block',
        'integrity_enforcement' => 'block',
    ])->assertRedirect();

    expect(AttendancePolicy::where('tenant_id', $this->admin->tenant_id)->firstOrFail()->attendance_scope)
        ->toBe('anywhere');
});

it('rejects an unknown attendance scope', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'attendance_scope' => 'mars',
        'face_enforcement' => 'block',
        'integrity_enforcement' => 'block',
    ])->assertSessionHasErrors('attendance_scope');
});

it('rejects an invalid enforcement value', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'face_enforcement' => 'ignore',
        'integrity_enforcement' => 'block',
    ])->assertSessionHasErrors('face_enforcement');
});

it('forbids a plain employee from managing the policy', function (): void {
    $employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($employee)->get('/avana/absensi/kebijakan')->assertForbidden();
});

it('gives one employee WFA without loosening the tenant policy', function (): void {
    $employee = Employee::forTenant($this->admin->tenant_id)->firstOrFail();

    actingAs($this->admin)->post('/avana/absensi/kebijakan/pengecualian', [
        'employee_id' => $employee->id,
        'attendance_scope' => 'anywhere',
    ])->assertRedirect()->assertSessionHas('success');

    expect($employee->fresh()->attendance_scope)->toBe('anywhere');

    // Everyone else still follows the tenant-wide scope.
    expect(Employee::forTenant($this->admin->tenant_id)
        ->whereKeyNot($employee->id)
        ->whereNotNull('attendance_scope')
        ->count())->toBe(0);
});

it('gives multiple employees the same attendance exception in one request', function (): void {
    $employees = Employee::forTenant($this->admin->tenant_id)
        ->where('status', 'active')
        ->whereNull('attendance_scope')
        ->take(2)
        ->get();

    actingAs($this->admin)->post('/avana/absensi/kebijakan/pengecualian', [
        'employee_ids' => $employees->pluck('id')->all(),
        'attendance_scope' => 'any_branch',
    ])->assertRedirect()->assertSessionHas('success');

    expect(Employee::forTenant($this->admin->tenant_id)
        ->whereIn('id', $employees->pluck('id'))
        ->where('attendance_scope', 'any_branch')
        ->count())->toBe(2);
});

it('saves a different attendance exception for each employee row', function (): void {
    $employees = Employee::forTenant($this->admin->tenant_id)
        ->where('status', 'active')
        ->whereNull('attendance_scope')
        ->take(2)
        ->get();

    actingAs($this->admin)->post('/avana/absensi/kebijakan/pengecualian', [
        'overrides' => [
            ['employee_id' => $employees[0]->id, 'attendance_scope' => 'anywhere'],
            ['employee_id' => $employees[1]->id, 'attendance_scope' => 'assigned'],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    expect($employees[0]->fresh()->attendance_scope)->toBe('anywhere');
    expect($employees[1]->fresh()->attendance_scope)->toBe('assigned');
});

it('lists the exceptions and offers only employees without one', function (): void {
    $employee = Employee::forTenant($this->admin->tenant_id)->firstOrFail();
    $employee->update(['attendance_scope' => 'anywhere']);

    actingAs($this->admin)->get('/avana/absensi/kebijakan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('scopeOptions', 3)
            ->has('overrides', 1)
            ->where('overrides.0.name', $employee->full_name)
            ->where('overrides.0.scope_label', 'Bebas di mana saja (WFA)')
            ->where('assignableEmployees', fn ($rows) => collect($rows)
                ->doesntContain(fn (array $row): bool => $row['id'] === $employee->id))
            ->etc());
});

it('puts an employee back on the tenant policy', function (): void {
    $employee = Employee::forTenant($this->admin->tenant_id)->firstOrFail();
    $employee->update(['attendance_scope' => 'anywhere']);

    actingAs($this->admin)
        ->delete('/avana/absensi/kebijakan/pengecualian/'.$employee->getRouteKey())
        ->assertRedirect();

    expect($employee->fresh()->attendance_scope)->toBeNull();
});

it('rejects an unknown scope for an exception', function (): void {
    $employee = Employee::forTenant($this->admin->tenant_id)->firstOrFail();

    actingAs($this->admin)->post('/avana/absensi/kebijakan/pengecualian', [
        'employee_id' => $employee->id,
        'attendance_scope' => 'mars',
    ])->assertSessionHasErrors('attendance_scope');

    expect($employee->fresh()->attendance_scope)->toBeNull();
});

it('refuses an exception for an employee of another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-absensi']);
    $outsider = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-OUT-1',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)->post('/avana/absensi/kebijakan/pengecualian', [
        'employee_id' => $outsider->id,
        'attendance_scope' => 'anywhere',
    ])->assertSessionHasErrors('employee_id');

    expect($outsider->fresh()->attendance_scope)->toBeNull();
});

it('forbids a plain employee from adding an exception', function (): void {
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($user)->post('/avana/absensi/kebijakan/pengecualian', [
        'employee_id' => $user->employee->id,
        'attendance_scope' => 'anywhere',
    ])->assertForbidden();

    expect($user->employee->fresh()->attendance_scope)->toBeNull();
});
