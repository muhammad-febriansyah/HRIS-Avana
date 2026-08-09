<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePenalty;
use App\Models\AttendancePenaltyRule;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Services\AttendanceCorrectionApproval;
use App\Services\AttendanceFinalizer;
use App\Support\AttendanceFines;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->tenant = Tenant::where('slug', 'nusantara')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $this->shift = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'FINALIZER-DAY',
        'name' => 'Finalizer Day',
        'start_time' => '08:00',
        'end_time' => '17:00',
        'late_tolerance_minutes' => 15,
        'status' => 'active',
    ]);
});

function scheduleFinalizerShift(object $test, string $date, ?Shift $shift = null): ShiftSchedule
{
    return ShiftSchedule::create([
        'tenant_id' => $test->tenant->id,
        'employee_id' => $test->employee->id,
        'shift_id' => ($shift ?? $test->shift)->id,
        'date' => $date,
    ]);
}

it('materializes an absent row and automatic penalty after the shift grace period', function (): void {
    scheduleFinalizerShift($this, '2026-09-10');

    $counts = app(AttendanceFinalizer::class)->finalizeRange(
        $this->tenant->id,
        '2026-09-10',
        '2026-09-10',
        now: Carbon::parse('2026-09-10 21:00', 'Asia/Jakarta'),
    );

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-09-10')
        ->firstOrFail();

    expect($counts['absent'])->toBe(1)
        ->and($attendance->status)->toBe('absent')
        ->and($attendance->shift_id)->toBe($this->shift->id);

    $penalty = AttendancePenalty::where('attendance_id', $attendance->id)->firstOrFail();
    expect($penalty->source)->toBe('automatic')
        ->and($penalty->violation_type)->toBe('absent');
});

it('marks a past clock-in without clock-out incomplete and stays idempotent', function (): void {
    scheduleFinalizerShift($this, '2026-09-11');
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'shift_id' => $this->shift->id,
        'date' => '2026-09-11',
        'clock_in_at' => '2026-09-11 08:10:00',
        'status' => 'present',
    ]);

    $finalizer = app(AttendanceFinalizer::class);
    $now = Carbon::parse('2026-09-11 21:00', 'Asia/Jakarta');
    $finalizer->finalizeRange($this->tenant->id, '2026-09-11', '2026-09-11', now: $now);
    $finalizer->finalizeRange($this->tenant->id, '2026-09-11', '2026-09-11', now: $now);

    expect(Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-09-11')->count())->toBe(1)
        ->and(Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-09-11')->value('status'))
        ->toBe('incomplete')
        ->and(AttendancePenalty::where('source', 'automatic')->count())->toBe(1);
});

it('waits until a night shift ends in the employee branch timezone', function (): void {
    $night = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'FINALIZER-NIGHT',
        'name' => 'Finalizer Night',
        'start_time' => '22:00',
        'end_time' => '06:00',
        'late_tolerance_minutes' => 15,
        'status' => 'active',
    ]);
    scheduleFinalizerShift($this, '2026-09-12', $night);

    $finalizer = app(AttendanceFinalizer::class);
    $beforeGrace = $finalizer->finalizeRange(
        $this->tenant->id,
        '2026-09-12',
        '2026-09-12',
        now: Carbon::parse('2026-09-13 08:59', 'Asia/Jakarta'),
    );

    expect($beforeGrace['not_due'])->toBe(1)
        ->and(Attendance::whereDate('date', '2026-09-12')->exists())->toBeFalse();

    $afterGrace = $finalizer->finalizeRange(
        $this->tenant->id,
        '2026-09-12',
        '2026-09-12',
        now: Carbon::parse('2026-09-13 09:00', 'Asia/Jakarta'),
    );

    expect($afterGrace['absent'])->toBe(1);
});

it('does not overwrite approved leave or explicit roster days off', function (): void {
    scheduleFinalizerShift($this, '2026-09-13');
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'shift_id' => $this->shift->id,
        'date' => '2026-09-13',
        'status' => 'leave',
    ]);

    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => null,
        'date' => '2026-09-14',
    ]);

    app(AttendanceFinalizer::class)->finalizeRange(
        $this->tenant->id,
        '2026-09-13',
        '2026-09-14',
        now: Carbon::parse('2026-09-15 12:00', 'Asia/Jakarta'),
    );

    expect(Attendance::whereDate('date', '2026-09-13')->value('status'))->toBe('leave')
        ->and(Attendance::whereDate('date', '2026-09-14')->exists())->toBeFalse();
});

it('supports an overnight attendance correction and calculates the true duration', function (): void {
    $night = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'CORRECTION-NIGHT',
        'name' => 'Correction Night',
        'start_time' => '22:00',
        'end_time' => '06:00',
        'late_tolerance_minutes' => 15,
        'status' => 'active',
    ]);
    scheduleFinalizerShift($this, '2026-09-15', $night);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-09-15',
        'requested_clock_in' => '22:00',
        'requested_clock_out' => '06:00',
        'reason' => 'Perbaikan shift malam',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, null);

    $attendance = $correction->fresh()->attendance;
    expect($attendance?->clock_out_at?->toDateString())->toBe('2026-09-16')
        ->and($attendance?->work_minutes)->toBe(480)
        ->and($attendance?->status)->toBe('present');
});

it('recalculates automatic penalties after rules change without touching manual penalties', function (): void {
    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'shift_id' => $this->shift->id,
        'date' => '2026-09-16',
        'clock_in_at' => '2026-09-16 08:20:00',
        'clock_out_at' => '2026-09-16 17:00:00',
        'status' => 'late',
        'late_minutes' => 20,
    ]);
    $rule = AttendancePenaltyRule::create([
        'tenant_id' => $this->tenant->id,
        'violation_type' => 'late',
        'min_minutes' => 10,
        'max_minutes' => 30,
        'penalty_type' => 'deduction',
        'amount' => 20000,
        'is_active' => true,
    ]);
    AttendanceFines::sync($attendance);
    $manual = AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-09-16',
        'violation_type' => 'late',
        'source' => 'manual',
        'penalty_type' => 'deduction',
        'amount' => 999,
        'status' => 'active',
    ]);

    $rule->update(['amount' => 35000]);
    AttendanceFines::refreshAutomaticForTenant($this->tenant->id);

    expect((float) AttendancePenalty::where('attendance_id', $attendance->id)->value('amount'))->toBe(35000.0)
        ->and((float) $manual->fresh()->amount)->toBe(999.0);
});

it('enforces one attendance row per employee work date even when shift is null', function (): void {
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-09-17',
        'status' => 'absent',
    ]);

    expect(fn () => Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-09-17',
        'status' => 'absent',
    ]))->toThrow(UniqueConstraintViolationException::class);
});
