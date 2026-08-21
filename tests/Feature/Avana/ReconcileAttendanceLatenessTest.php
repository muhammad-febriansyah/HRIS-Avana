<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();
    $this->shift = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PAGI9',
        'name' => 'Pagi',
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'late_tolerance_minutes' => 0,
        'status' => 'active',
    ]);
});

it('re-judges a clocked-in row against a roster shift assigned after the fact', function (): void {
    // The shift landed on the roster after the punch — exactly the race the
    // command backfills.
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => '2026-06-29',
    ]);

    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-06-29',
        'clock_in_at' => '2026-06-29 09:21:00',
        'status' => 'present',
        'late_minutes' => 0,
    ]);

    artisan('avana:reconcile-attendance-lateness', [
        '--tenant' => $this->tenant->id,
        '--from' => '2026-06-01',
        '--to' => '2026-06-30',
    ])->assertSuccessful();

    $attendance->refresh();

    expect($attendance->shift_id)->toBe($this->shift->id);
    expect($attendance->status)->toBe('late');
    expect($attendance->late_minutes)->toBe(21);
});

it('leaves a row untouched when no schedule exists for its date', function (): void {
    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-06-29',
        'clock_in_at' => '2026-06-29 09:21:00',
        'status' => 'present',
        'late_minutes' => 0,
    ]);

    artisan('avana:reconcile-attendance-lateness', [
        '--tenant' => $this->tenant->id,
        '--from' => '2026-06-01',
        '--to' => '2026-06-30',
    ])->assertSuccessful();

    $attendance->refresh();

    expect($attendance->status)->toBe('present');
    expect($attendance->late_minutes)->toBe(0);
});

it('leaves a leave-marked row untouched even when a shift exists for its date', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => '2026-06-29',
    ]);

    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-06-29',
        'clock_in_at' => '2026-06-29 09:21:00',
        'status' => 'leave',
        'late_minutes' => 0,
    ]);

    artisan('avana:reconcile-attendance-lateness', [
        '--tenant' => $this->tenant->id,
        '--from' => '2026-06-01',
        '--to' => '2026-06-30',
    ])->assertSuccessful();

    $attendance->refresh();

    expect($attendance->status)->toBe('leave');
});

it('does not touch attendance rows outside the requested date range', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => $this->shift->id,
        'date' => '2026-07-15',
    ]);

    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-07-15',
        'clock_in_at' => '2026-07-15 09:21:00',
        'status' => 'present',
        'late_minutes' => 0,
    ]);

    artisan('avana:reconcile-attendance-lateness', [
        '--tenant' => $this->tenant->id,
        '--from' => '2026-06-01',
        '--to' => '2026-06-30',
    ])->assertSuccessful();

    $attendance->refresh();

    expect($attendance->status)->toBe('present');
    expect($attendance->late_minutes)->toBe(0);
});
