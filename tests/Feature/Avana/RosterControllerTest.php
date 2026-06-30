<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftSchedule;
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
    $this->shift = Shift::forTenant($this->tenant->id)->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();
});

/**
 * The Monday used by the deterministic-window tests.
 */
const WEEK_START = '2026-06-29';

it('renders the roster index with the expected props', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => WEEK_START,
    ]);

    actingAs($this->admin)
        ->get(route('avana.roster', ['week_start' => WEEK_START]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/roster/index', false)
            ->where('week_start', WEEK_START)
            ->has('week', 7)
            ->has('week.0', fn (Assert $day) => $day
                ->where('date', WEEK_START)
                ->has('label')
                ->has('dow')
                ->has('day'))
            ->has('employees')
            ->has('employees.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('employee_number'))
            ->has('shifts')
            ->has('shifts.0', fn (Assert $row) => $row
                ->has('id')
                ->has('code')
                ->has('name')
                ->has('start_time')
                ->has('end_time'))
            ->has('schedules', 1)
            ->has('schedules.0', fn (Assert $row) => $row
                ->has('id')
                ->where('employee_id', $this->employee->id)
                ->where('shift_id', $this->shift->id)
                ->where('date', WEEK_START)));
});

it('defaults to the current week when no week_start is supplied', function (): void {
    $monday = now()->startOfWeek(Carbon\Carbon::MONDAY)->format('Y-m-d');

    actingAs($this->admin)
        ->get(route('avana.roster'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('week_start', $monday));
});

it('only returns schedules within the requested week and tenant', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => WEEK_START,
    ]);

    // Outside the 7-day window.
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => '2026-07-20',
    ]);

    // Another tenant entirely.
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-roster']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreignShift = Shift::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'X',
        'name' => 'Asing',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 'active',
    ]);
    ShiftSchedule::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'shift_id' => $foreignShift->id,
        'date' => WEEK_START,
    ]);

    actingAs($this->admin)
        ->get(route('avana.roster', ['week_start' => WEEK_START]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('schedules', 1));
});

it('assigns a shift to an employee on a date', function (): void {
    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date' => WEEK_START,
        ])
        ->assertSessionHas('success');

    $schedule = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('date', WEEK_START)
        ->firstOrFail();

    expect($schedule->shift_id)->toBe($this->shift->id);
});

it('updates the existing assignment instead of duplicating on re-store', function (): void {
    $secondShift = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SIANG',
        'name' => 'Siang',
        'start_time' => '13:00:00',
        'end_time' => '21:00:00',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date' => WEEK_START,
        ])
        ->assertSessionHas('success');

    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => $this->employee->id,
            'shift_id' => $secondShift->id,
            'date' => WEEK_START,
        ])
        ->assertSessionHas('success');

    $schedules = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('date', WEEK_START)
        ->get();

    expect($schedules)->toHaveCount(1);
    expect($schedules->first()->shift_id)->toBe($secondShift->id);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => '',
            'shift_id' => '',
            'date' => '',
        ])
        ->assertSessionHasErrors(['employee_id', 'shift_id', 'date']);
});

it('deletes a schedule', function (): void {
    $schedule = ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => WEEK_START,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.roster.destroy', $schedule))
        ->assertSessionHas('success');

    expect(ShiftSchedule::find($schedule->id))->toBeNull();
});

it('returns 404 when deleting a schedule from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-roster']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreignShift = Shift::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'X',
        'name' => 'Asing',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'status' => 'active',
    ]);
    $foreign = ShiftSchedule::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'shift_id' => $foreignShift->id,
        'date' => WEEK_START,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.roster.destroy', $foreign))
        ->assertNotFound();

    expect(ShiftSchedule::find($foreign->id))->not->toBeNull();
});

it('forbids users without attendance permissions from viewing the roster', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.roster'))
        ->assertForbidden();
});
