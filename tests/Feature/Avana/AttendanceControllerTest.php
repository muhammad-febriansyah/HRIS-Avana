<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
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
 * The fixed date used by the tests so KPIs stay deterministic and never
 * collide with the seeder's "today" rekap rows.
 */
const TEST_DATE = '2026-08-15';

/**
 * Create an attendance row for the seeded tenant on the test date.
 */
function makeAttendance(int $tenantId, int $shiftId, array $overrides = []): Attendance
{
    $employee = Employee::forTenant($tenantId)->firstOrFail();

    return Attendance::create(array_merge([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'shift_id' => $shiftId,
        'date' => TEST_DATE,
        'clock_in_at' => TEST_DATE.' 08:00:00',
        'clock_out_at' => TEST_DATE.' 17:00:00',
        'late_minutes' => 0,
        'work_minutes' => 540,
        'status' => 'present',
        'location_status' => 'inside',
    ], $overrides));
}

it('renders the paginated absensi index with the expected props', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id);

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => TEST_DATE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi', false)
            ->has('attendances.data')
            ->has('attendances.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee.name')
                ->has('employee.initials')
                ->has('employee.avatar_color')
                ->has('shift.label')
                ->has('date')
                ->has('clock_in')
                ->has('clock_out')
                ->has('late_minutes')
                ->has('telat')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->where('filters.date', TEST_DATE)
            ->where('date.value', TEST_DATE)
            ->has('date.display')
            ->has('kpis.hadir')
            ->has('kpis.terlambat')
            ->has('kpis.izin')
            ->has('kpis.alpa')
            ->has('branches'));
});

it('defaults to today when no date filter is supplied', function (): void {
    $today = now()->format('Y-m-d');

    actingAs($this->admin)
        ->get(route('avana.absensi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date.value', $today));
});

it('only lists attendances that belong to the current tenant', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-absensi']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9999',
        'full_name' => 'Karyawan Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    Attendance::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'date' => TEST_DATE,
        'status' => 'present',
        'late_minutes' => 0,
        'work_minutes' => 0,
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => TEST_DATE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('attendances.data', 1));
});

it('filters the rekap by the requested date', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id, ['date' => TEST_DATE]);
    makeAttendance($this->tenant->id, $this->shift->id, ['date' => '2026-08-16']);

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => TEST_DATE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attendances.data', 1)
            ->where('attendances.data.0.date_raw', TEST_DATE));
});

it('computes the status KPIs for the selected date with a grouped query', function (): void {
    $employees = Employee::forTenant($this->tenant->id)->take(4)->get();

    foreach (['present', 'late', 'leave', 'absent'] as $i => $status) {
        Attendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employees[$i]->id,
            'branch_id' => $employees[$i]->branch_id,
            'shift_id' => $this->shift->id,
            'date' => TEST_DATE,
            'late_minutes' => $status === 'late' ? 20 : 0,
            'work_minutes' => 0,
            'status' => $status,
        ]);
    }

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => TEST_DATE]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.hadir', 1)
            ->where('kpis.terlambat', 1)
            ->where('kpis.izin', 1)
            ->where('kpis.alpa', 1));
});

it('approves an attendance correction and syncs the linked attendance', function (): void {
    $attendance = makeAttendance($this->tenant->id, $this->shift->id, [
        'status' => 'need_correction',
        'clock_in_at' => null,
    ]);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'attendance_id' => $attendance->id,
        'employee_id' => $attendance->employee_id,
        'date' => TEST_DATE,
        'correction_type' => 'clock_in',
        'requested_clock_in' => '08:00:00',
        'reason' => 'Lupa absen masuk',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.absensi.corrections.approve', $correction))
        ->assertSessionHas('success');

    expect($correction->fresh()->status)->toBe('approved');
    expect($correction->fresh()->approver_id)->toBe($this->admin->id);
    expect($attendance->fresh()->status)->toBe('present');
    expect($attendance->fresh()->clock_in_at?->format('H:i'))->toBe('08:00');
});

it('rejects an attendance correction', function (): void {
    $attendance = makeAttendance($this->tenant->id, $this->shift->id, ['status' => 'need_correction']);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'attendance_id' => $attendance->id,
        'employee_id' => $attendance->employee_id,
        'date' => TEST_DATE,
        'correction_type' => 'clock_in',
        'requested_clock_in' => '08:00:00',
        'reason' => 'Salah jam',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.absensi.corrections.reject', $correction))
        ->assertSessionHas('success');

    expect($correction->fresh()->status)->toBe('rejected');
});

it('returns 404 when approving a correction from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-absensi']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-0001',
        'full_name' => 'Foreign Worker',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $correction = AttendanceCorrection::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'date' => TEST_DATE,
        'correction_type' => 'clock_in',
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.absensi.corrections.approve', $correction))
        ->assertNotFound();

    expect($correction->fresh()->status)->toBe('pending');
});

it('forbids users without attendance permissions from listing the rekap', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.absensi'))
        ->assertForbidden();
});
