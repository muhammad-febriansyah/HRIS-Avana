<?php

use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Shift;
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
});

/**
 * The fixed range used by the generate tests so results stay deterministic
 * and never collide with the seeder's "today" rekap rows.
 */
const PENALTY_RANGE_START = '2026-08-10';
const PENALTY_RANGE_END = '2026-08-20';

/**
 * Create an attendance row for the seeded tenant within the test range.
 */
function makePenaltyAttendance(int $tenantId, int $shiftId, array $overrides = []): Attendance
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return Attendance::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'shift_id' => $shiftId,
        'date' => '2026-08-15',
        'late_minutes' => 0,
        'work_minutes' => 0,
        'status' => 'late',
    ], $overrides));
}

it('renders the paginated sanksi index with the expected props', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'date' => '2026-08-15',
        'violation_type' => 'late',
        'penalty_type' => 'deduction',
        'amount' => 50000,
        'notes' => 'Terlambat berulang',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->get(route('avana.sanksi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/sanksi', false)
            ->has('penalties.data', 1)
            ->has('penalties.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.employee_number')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('date')
                ->has('date_raw')
                ->where('violation_type', 'late')
                ->where('penalty_type', 'deduction')
                ->where('amount', 50000)
                ->has('notes')
                ->where('status', 'active'))
            ->has('penalties.meta')
            ->has('employees')
            ->has('filters'));
});

it('only lists penalties that belong to the current tenant', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'date' => '2026-08-15',
        'violation_type' => 'late',
        'penalty_type' => 'warning',
        'amount' => 0,
        'status' => 'active',
    ]);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-sanksi']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    AttendancePenalty::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'date' => '2026-08-15',
        'violation_type' => 'absent',
        'penalty_type' => 'warning',
        'amount' => 0,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->get(route('avana.sanksi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('penalties.data', 1));
});

it('creates a manual penalty for an employee', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.sanksi.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-08-15',
            'violation_type' => 'early_leave',
            'penalty_type' => 'deduction',
            'amount' => 75000,
            'notes' => 'Pulang sebelum jam kerja selesai',
        ])
        ->assertRedirect(route('avana.sanksi'))
        ->assertSessionHas('success');

    $penalty = AttendancePenalty::where('employee_id', $employee->id)->latest('id')->firstOrFail();

    expect($penalty->tenant_id)->toBe($this->tenant->id);
    expect($penalty->violation_type)->toBe('early_leave');
    expect($penalty->penalty_type)->toBe('deduction');
    expect((float) $penalty->amount)->toBe(75000.0);
    expect($penalty->status)->toBe('active');
});

it('requires an amount when the penalty type is a deduction', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.sanksi.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-08-15',
            'violation_type' => 'late',
            'penalty_type' => 'deduction',
        ])
        ->assertSessionHasErrors(['amount']);
});

it('validates required fields and the violation type on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sanksi.store'), [
            'employee_id' => '',
            'date' => '',
            'violation_type' => 'unknown',
            'penalty_type' => 'invalid',
        ])
        ->assertSessionHasErrors(['employee_id', 'date', 'violation_type', 'penalty_type']);
});

it('generates penalties from late and absent attendance in the range', function (): void {
    $employees = Employee::forTenant($this->tenant->id)->take(2)->get();

    makePenaltyAttendance($this->tenant->id, $this->shift->id, [
        'employee_id' => $employees[0]->id,
        'date' => '2026-08-15',
        'status' => 'late',
        'late_minutes' => 30,
    ]);
    makePenaltyAttendance($this->tenant->id, $this->shift->id, [
        'employee_id' => $employees[1]->id,
        'date' => '2026-08-16',
        'status' => 'absent',
    ]);
    // present rows should never produce a penalty
    makePenaltyAttendance($this->tenant->id, $this->shift->id, [
        'employee_id' => $employees[0]->id,
        'date' => '2026-08-17',
        'status' => 'present',
    ]);
    // out-of-range row should be ignored
    makePenaltyAttendance($this->tenant->id, $this->shift->id, [
        'employee_id' => $employees[1]->id,
        'date' => '2026-09-01',
        'status' => 'late',
    ]);

    actingAs($this->admin)
        ->post(route('avana.sanksi.generate'), [
            'start_date' => PENALTY_RANGE_START,
            'end_date' => PENALTY_RANGE_END,
        ])
        ->assertRedirect(route('avana.sanksi'))
        ->assertSessionHas('success');

    expect(AttendancePenalty::where('tenant_id', $this->tenant->id)->count())->toBe(2);

    $late = AttendancePenalty::where('violation_type', 'late')->firstOrFail();
    expect($late->penalty_type)->toBe('warning');
    expect((float) $late->amount)->toBe(0.0);
});

it('does not create duplicate penalties when generated twice', function (): void {
    makePenaltyAttendance($this->tenant->id, $this->shift->id, [
        'date' => '2026-08-15',
        'status' => 'late',
        'late_minutes' => 15,
    ]);

    $payload = [
        'start_date' => PENALTY_RANGE_START,
        'end_date' => PENALTY_RANGE_END,
    ];

    actingAs($this->admin)->post(route('avana.sanksi.generate'), $payload);
    $countAfterFirst = AttendancePenalty::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)->post(route('avana.sanksi.generate'), $payload);
    $countAfterSecond = AttendancePenalty::where('tenant_id', $this->tenant->id)->count();

    expect($countAfterFirst)->toBe(1);
    expect($countAfterSecond)->toBe(1);
});

it('deletes a penalty', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $penalty = AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'date' => '2026-08-15',
        'violation_type' => 'late',
        'penalty_type' => 'warning',
        'amount' => 0,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sanksi.destroy', $penalty))
        ->assertSessionHas('success');

    expect(AttendancePenalty::find($penalty->id))->toBeNull();
});

it('returns 404 when deleting a penalty from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-sanksi']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $foreign = AttendancePenalty::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'date' => '2026-08-15',
        'violation_type' => 'late',
        'penalty_type' => 'warning',
        'amount' => 0,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sanksi.destroy', $foreign))
        ->assertNotFound();

    expect(AttendancePenalty::find($foreign->id))->not->toBeNull();
});

it('forbids users without attendance permissions from listing penalties', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.sanksi'))
        ->assertForbidden();
});
