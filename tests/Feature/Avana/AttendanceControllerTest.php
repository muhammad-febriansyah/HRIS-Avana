<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSelfie;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
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
    $this->shift = Shift::forTenant($this->tenant->id)->firstOrFail();
});

/**
 * The date these tests write attendance on.
 *
 * Derived from today rather than hard-coded: the seeder fills in a rekap for
 * "today", so a fixed date breaks the whole file on the one day of the year it
 * happens to match — which is exactly what it did. Far enough ahead that no
 * seeded row can reach it, and stable within a run so the KPIs stay
 * deterministic.
 */
function attendanceTestDate(): string
{
    return today()->addDays(45)->toDateString();
}

/** The day after, used by the range tests. */
function attendanceTestDate2(): string
{
    return today()->addDays(46)->toDateString();
}

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
        'date' => attendanceTestDate(),
        'clock_in_at' => attendanceTestDate().' 08:00:00',
        'clock_out_at' => attendanceTestDate().' 17:00:00',
        'late_minutes' => 0,
        'work_minutes' => 540,
        'status' => 'present',
        'location_status' => 'inside',
    ], $overrides));
}

it('renders the paginated absensi index with the expected props', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id);

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi/index', false)
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
            ->where('filters.date_from', attendanceTestDate())
            ->where('filters.date_to', attendanceTestDate())
            ->where('range.from', attendanceTestDate())
            ->where('range.to', attendanceTestDate())
            ->has('range.display')
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
        ->assertInertia(fn (Assert $page) => $page->where('range.from', $today));
});

it('aggregates the absensi index across a date_from..date_to range', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id, [
        'date' => attendanceTestDate(), 'clock_in_at' => attendanceTestDate().' 08:00:00', 'status' => 'present',
    ]);
    makeAttendance($this->tenant->id, $this->shift->id, [
        'date' => attendanceTestDate2(), 'clock_in_at' => attendanceTestDate2().' 09:30:00', 'status' => 'late',
    ]);
    // Outside the range — excluded.
    makeAttendance($this->tenant->id, $this->shift->id, [
        'date' => '2026-08-20', 'clock_in_at' => '2026-08-20 08:00:00', 'status' => 'present',
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi', [
            'date_from' => attendanceTestDate(),
            'date_to' => today()->addDays(46)->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.from', attendanceTestDate())
            ->where('range.to', today()->addDays(46)->toDateString())
            ->where('range.is_range', true)
            ->where('kpis.hadir', 1)
            ->where('kpis.terlambat', 1)
            ->has('attendances.data', 2)
            ->etc());
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
        'date' => attendanceTestDate(),
        'status' => 'present',
        'late_minutes' => 0,
        'work_minutes' => 0,
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('attendances.data', 1));
});

it('filters the rekap by the requested date', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id, ['date' => attendanceTestDate()]);
    makeAttendance($this->tenant->id, $this->shift->id, ['date' => attendanceTestDate2()]);

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('attendances.data', 1)
            ->where('attendances.data.0.date_raw', attendanceTestDate()));
});

it('computes the status KPIs for the selected date with a grouped query', function (): void {
    $employees = Employee::forTenant($this->tenant->id)->take(4)->get();

    foreach (['present', 'late', 'leave', 'absent'] as $i => $status) {
        Attendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employees[$i]->id,
            'branch_id' => $employees[$i]->branch_id,
            'shift_id' => $this->shift->id,
            'date' => attendanceTestDate(),
            'late_minutes' => $status === 'late' ? 20 : 0,
            'work_minutes' => 0,
            'status' => $status,
        ]);
    }

    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => attendanceTestDate()]))
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
        'date' => attendanceTestDate(),
        'correction_type' => 'clock_in',
        'requested_clock_in' => '08:00:00',
        'reason' => 'Lupa absen masuk',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'koreksi', 'id' => $correction->id]))
        ->assertSessionHas('success');

    expect($correction->fresh()->status)->toBe('approved');
    expect($correction->fresh()->approver_id)->toBe($this->admin->id);
    expect($attendance->fresh()->status)->toBe('present');
    expect($attendance->fresh()->clock_in_at?->format('H:i'))->toBe('08:00');
});

it('does not process an approved attendance correction twice', function (): void {
    $attendance = makeAttendance($this->tenant->id, $this->shift->id, [
        'status' => 'need_correction',
        'clock_in_at' => null,
    ]);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'attendance_id' => $attendance->id,
        'employee_id' => $attendance->employee_id,
        'date' => attendanceTestDate(),
        'correction_type' => 'clock_in',
        'requested_clock_in' => '08:00:00',
        'reason' => 'Lupa absen masuk',
        'status' => 'pending',
    ]);

    $route = route('avana.approval.approve', ['type' => 'koreksi', 'id' => $correction->id]);

    actingAs($this->admin)->post($route)->assertSessionHas('success');
    $firstApproverId = $correction->fresh()->approver_id;

    actingAs($this->admin)->post($route)->assertUnprocessable();

    expect($correction->fresh()->status)->toBe('approved')
        ->and($correction->fresh()->approver_id)->toBe($firstApproverId)
        ->and(Attendance::whereKey($attendance->id)->count())->toBe(1);
});

it('rejects an attendance correction', function (): void {
    $attendance = makeAttendance($this->tenant->id, $this->shift->id, ['status' => 'need_correction']);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'attendance_id' => $attendance->id,
        'employee_id' => $attendance->employee_id,
        'date' => attendanceTestDate(),
        'correction_type' => 'clock_in',
        'requested_clock_in' => '08:00:00',
        'reason' => 'Salah jam',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval.reject', ['type' => 'koreksi', 'id' => $correction->id]))
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
        'date' => attendanceTestDate(),
        'correction_type' => 'clock_in',
        'reason' => 'Test',
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.approval.approve', ['type' => 'koreksi', 'id' => $correction->id]))
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

it('renders the attendance detail page with the expected props', function (): void {
    $attendance = makeAttendance($this->tenant->id, $this->shift->id, [
        'clock_in_lat' => -6.2146,
        'clock_in_lng' => 106.8451,
        'location_status' => 'inside',
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi.show', $attendance))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi/show', false)
            ->where('attendance.id', $attendance->id)
            ->has('attendance.employee.name')
            ->has('attendance.clock_in')
            ->has('attendance.clock_out')
            ->has('attendance.selfies'));
});

it('returns 404 for an attendance detail from another tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-att']);
    $employee = Employee::create([
        'tenant_id' => $other->id,
        'employee_number' => 'EMP-8888',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $attendance = Attendance::create([
        'tenant_id' => $other->id,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'date' => attendanceTestDate(),
        'status' => 'present',
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi.show', $attendance))
        ->assertNotFound();
});

it('renders the live monitor with map points, KPIs, and recent activity', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id, [
        'clock_in_lat' => -6.2000,
        'clock_in_lng' => 106.8166,
        'status' => 'present',
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi.monitor', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi/monitor', false)
            ->has('kpis', fn (Assert $kpis) => $kpis
                ->has('total_personnel')
                ->has('on_time')
                ->has('late')
                ->has('no_show'))
            ->has('points.0', fn (Assert $point) => $point
                ->where('lat', -6.2)
                ->where('lng', 106.8166)
                ->has('label')
                ->has('status'))
            ->has('activity.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('location')
                ->has('branch')
                ->has('time')
                ->has('date')
                ->has('status')
                ->has('status_label')));
});

it('aggregates the monitor across a date_from..date_to range', function (): void {
    // Two check-ins on different days within the range.
    makeAttendance($this->tenant->id, $this->shift->id, [
        'date' => attendanceTestDate(),
        'clock_in_at' => attendanceTestDate().' 08:00:00',
        'status' => 'present',
    ]);
    makeAttendance($this->tenant->id, $this->shift->id, [
        'date' => attendanceTestDate2(),
        'clock_in_at' => attendanceTestDate2().' 09:30:00',
        'status' => 'late',
    ]);
    // Outside the range — must be excluded.
    makeAttendance($this->tenant->id, $this->shift->id, [
        'date' => '2026-08-20',
        'clock_in_at' => '2026-08-20 08:00:00',
        'status' => 'present',
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi.monitor', [
            'date_from' => attendanceTestDate(),
            'date_to' => today()->addDays(46)->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('range.from', attendanceTestDate())
            ->where('range.to', today()->addDays(46)->toDateString())
            ->where('filters.date_from', attendanceTestDate())
            ->where('filters.date_to', attendanceTestDate2())
            ->where('kpis.on_time', 1)
            ->where('kpis.late', 1)
            // Feed rows carry a day tag when the range spans multiple days.
            ->where('activity.0.date', today()->addDays(46)->format('d M'))
            ->has('activity', 2)
            ->etc());
});

it('shows the branch name alongside the work location in the activity feed', function (): void {
    $workLocation = WorkLocation::forTenant($this->tenant->id)->firstOrFail();
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    makeAttendance($this->tenant->id, $this->shift->id, [
        'work_location_id' => $workLocation->id,
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi.monitor', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.location', $workLocation->name)
            ->where('activity.0.branch', $employee->branch->name)
            ->etc());
});

it('omits the branch when it already stands in as the activity location', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id, [
        'work_location_id' => null,
    ]);

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->get(route('avana.absensi.monitor', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activity.0.location', $employee->branch->name)
            ->where('activity.0.branch', null)
            ->etc());
});

it('excludes attendance without GPS coordinates from monitor map points', function (): void {
    makeAttendance($this->tenant->id, $this->shift->id, [
        'clock_in_lat' => null,
        'clock_in_lng' => null,
    ]);

    actingAs($this->admin)
        ->get(route('avana.absensi.monitor', ['date' => attendanceTestDate()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('points', 0));
});

it('deletes an attendance record and its selfie photo file', function (): void {
    Storage::fake('local');
    $attendance = makeAttendance($this->tenant->id, $this->shift->id);
    $path = UploadedFile::fake()->image('selfie.jpg')->store('selfies', 'local');
    $selfie = AttendanceSelfie::create([
        'tenant_id' => $this->tenant->id,
        'attendance_id' => $attendance->id,
        'employee_id' => $attendance->employee_id,
        'file_path' => $path,
    ]);

    Storage::disk('local')->assertExists($path);

    actingAs($this->admin)
        ->delete(route('avana.absensi.destroy', $attendance))
        ->assertRedirect(route('avana.absensi'));

    expect(Attendance::find($attendance->id))->toBeNull();
    expect(AttendanceSelfie::find($selfie->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

it('returns 404 when deleting an attendance from another tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing-del']);
    $employee = Employee::create([
        'tenant_id' => $other->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);
    $attendance = Attendance::create([
        'tenant_id' => $other->id,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'date' => attendanceTestDate(),
        'status' => 'present',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.absensi.destroy', $attendance))
        ->assertNotFound();

    expect(Attendance::find($attendance->id))->not->toBeNull();
});
