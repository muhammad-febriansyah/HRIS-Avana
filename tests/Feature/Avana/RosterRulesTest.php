<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwap;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AttendanceCorrectionApproval;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    [$this->alice, $this->bob] = Employee::forTenant($this->tenant->id)->orderBy('id')->take(2)->get()->all();

    $this->pagi = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PAGI-SPEC', 'name' => 'Shift Pagi',
        'start_time' => '08:00:00', 'end_time' => '16:00:00',
        'late_tolerance_minutes' => 10, 'status' => 'active',
    ]);

    $this->siang = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SIANG-SPEC', 'name' => 'Shift Siang',
        'start_time' => '14:00:00', 'end_time' => '22:00:00',
        'late_tolerance_minutes' => 10, 'status' => 'active',
    ]);

    // A Monday, so weekday/weekend cases are unambiguous.
    $this->date = '2026-08-10';
});

/** The shift an employee is rostered onto for the test date. */
function rosteredShift(object $ctx, Employee $employee): ?string
{
    return ShiftSchedule::forTenant($ctx->tenant->id)
        ->where('employee_id', $employee->id)
        ->whereDate('date', $ctx->date)
        ->first()?->shift?->name;
}

it('swaps the roster when a shift swap is approved', function (): void {
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id, 'date' => $this->date, 'shift_id' => $this->pagi->id]);
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id, 'date' => $this->date, 'shift_id' => $this->siang->id]);

    $swap = ShiftSwap::create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->alice->id,
        'target_id' => $this->bob->id,
        'date' => $this->date,
        'requester_shift_id' => $this->pagi->id,
        'target_shift_id' => $this->siang->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)
        ->post(route('avana.shift-swap.approve', $swap))
        ->assertSessionHas('success');

    expect($swap->fresh()->status)->toBe('approved');
    expect(rosteredShift($this, $this->alice))->toBe('Shift Siang');
    expect(rosteredShift($this, $this->bob))->toBe('Shift Pagi');
});

it('leaves the roster alone when a swap is rejected', function (): void {
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id, 'date' => $this->date, 'shift_id' => $this->pagi->id]);
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id, 'date' => $this->date, 'shift_id' => $this->siang->id]);

    $swap = ShiftSwap::create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->alice->id, 'target_id' => $this->bob->id,
        'date' => $this->date,
        'requester_shift_id' => $this->pagi->id, 'target_shift_id' => $this->siang->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)->post(route('avana.shift-swap.reject', $swap))->assertSessionHas('success');

    expect(rosteredShift($this, $this->alice))->toBe('Shift Pagi');
    expect(rosteredShift($this, $this->bob))->toBe('Shift Siang');
});

it('does not swap twice when an approved swap is approved again', function (): void {
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id, 'date' => $this->date, 'shift_id' => $this->pagi->id]);
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->bob->id, 'date' => $this->date, 'shift_id' => $this->siang->id]);

    $swap = ShiftSwap::create([
        'tenant_id' => $this->tenant->id,
        'requester_id' => $this->alice->id, 'target_id' => $this->bob->id,
        'date' => $this->date,
        'requester_shift_id' => $this->pagi->id, 'target_shift_id' => $this->siang->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin)->post(route('avana.shift-swap.approve', $swap));
    actingAs($this->admin)->post(route('avana.shift-swap.approve', $swap));

    // Swapping back would undo the approval the second time round.
    expect(rosteredShift($this, $this->alice))->toBe('Shift Siang');
    expect(rosteredShift($this, $this->bob))->toBe('Shift Pagi');
});

it('keeps an approved correction late when the corrected time is late', function (): void {
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id, 'date' => $this->date, 'shift_id' => $this->pagi->id]);

    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'branch_id' => $this->alice->branch_id,
        'date' => $this->date,
        'clock_in_at' => $this->date.' 10:00:00',
        'status' => 'late',
        'late_minutes' => 120,
    ]);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'attendance_id' => $attendance->id,
        'date' => $this->date,
        'requested_clock_in' => '09:30',
        'reason' => 'Lupa absen',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, $this->admin->id);

    $fresh = $attendance->fresh();

    // 09:30 against an 08:00 shift is still late — 90 minutes, not "present".
    expect($fresh->status)->toBe('late');
    expect((int) $fresh->late_minutes)->toBe(90);
    expect((int) $fresh->shift_id)->toBe($this->pagi->id);
});

it('clears the lateness when the corrected time is inside the shift', function (): void {
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id, 'date' => $this->date, 'shift_id' => $this->pagi->id]);

    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'branch_id' => $this->alice->branch_id,
        'date' => $this->date,
        'clock_in_at' => $this->date.' 10:00:00',
        'status' => 'late',
        'late_minutes' => 120,
    ]);

    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'attendance_id' => $attendance->id,
        'date' => $this->date,
        'requested_clock_in' => '07:55',
        'reason' => 'Absen gagal',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, $this->admin->id);

    $fresh = $attendance->fresh();

    expect($fresh->status)->toBe('present');
    expect((int) $fresh->late_minutes)->toBe(0);
});

it('recalculates worked minutes from the effective times on a partial correction', function (): void {
    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'branch_id' => $this->alice->branch_id,
        'date' => $this->date,
        'clock_in_at' => $this->date.' 09:00:00',
        'clock_out_at' => $this->date.' 16:00:00',
        'work_minutes' => 420,
        'status' => 'present',
    ]);
    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'attendance_id' => $attendance->id,
        'date' => $this->date,
        'requested_clock_in' => '08:00',
        'reason' => 'Jam masuk salah',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, $this->admin->id);

    expect($attendance->fresh()->clock_in_at?->format('H:i'))->toBe('08:00')
        ->and($attendance->fresh()->clock_out_at?->format('H:i'))->toBe('16:00')
        ->and($attendance->fresh()->work_minutes)->toBe(480);
});

it('recalculates worked minutes when only clock out is corrected', function (): void {
    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'branch_id' => $this->alice->branch_id,
        'date' => $this->date,
        'clock_in_at' => $this->date.' 08:00:00',
        'clock_out_at' => $this->date.' 17:00:00',
        'work_minutes' => 540,
        'status' => 'present',
    ]);
    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'attendance_id' => $attendance->id,
        'date' => $this->date,
        'requested_clock_out' => '16:00',
        'reason' => 'Jam pulang salah',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, $this->admin->id);

    expect($attendance->fresh()->clock_in_at?->format('H:i'))->toBe('08:00')
        ->and($attendance->fresh()->clock_out_at?->format('H:i'))->toBe('16:00')
        ->and($attendance->fresh()->work_minutes)->toBe(480);
});

it('stores a night shift correction across midnight on the roster work date', function (): void {
    $malam = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MALAM-CORR',
        'name' => 'Shift Malam Koreksi',
        'start_time' => '22:00:00',
        'end_time' => '06:00:00',
        'late_tolerance_minutes' => 10,
        'status' => 'active',
    ]);
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => $this->date,
        'shift_id' => $malam->id,
    ]);
    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => $this->date,
        'requested_clock_in' => '22:00',
        'requested_clock_out' => '06:00',
        'reason' => 'Lupa absen shift malam',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, $this->admin->id);

    $attendance = $correction->fresh()->attendance;

    expect($attendance->date->toDateString())->toBe($this->date)
        ->and($attendance->clock_in_at?->format('Y-m-d H:i'))->toBe($this->date.' 22:00')
        ->and($attendance->clock_out_at?->format('Y-m-d H:i'))->toBe('2026-08-11 06:00')
        ->and($attendance->work_minutes)->toBe(480)
        ->and($attendance->shift_id)->toBe($malam->id);
});

it('keeps attendance correction finalization idempotent', function (): void {
    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => $this->date,
        'requested_clock_in' => '08:00',
        'reason' => 'Lupa absen',
        'status' => 'pending',
    ]);

    AttendanceCorrectionApproval::finalize($correction, $this->admin->id);
    AttendanceCorrectionApproval::finalize($correction, $this->admin->id + 1);

    expect($correction->fresh()->approver_id)->toBe($this->admin->id)
        ->and(Attendance::forTenant($this->tenant->id)
            ->where('employee_id', $this->alice->id)
            ->whereDate('date', $this->date)
            ->count())->toBe(1);
});

it('rolls back the attendance write when finalizing the correction fails', function (): void {
    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => $this->date,
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '16:00',
        'reason' => 'Lupa absen',
        'status' => 'pending',
    ]);

    expect(fn () => AttendanceCorrectionApproval::finalize($correction, 999999999))
        ->toThrow(QueryException::class);

    expect($correction->fresh()->status)->toBe('pending')
        ->and($correction->fresh()->attendance_id)->toBeNull()
        ->and(Attendance::forTenant($this->tenant->id)
            ->where('employee_id', $this->alice->id)
            ->whereDate('date', $this->date)
            ->exists())->toBeFalse();
});

it('marks a day off from the web roster', function (): void {
    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => $this->alice->id,
            'shift_id' => null,
            'date' => $this->date,
        ])
        ->assertSessionHas('success');

    $schedule = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->alice->id)
        ->whereDate('date', $this->date)
        ->firstOrFail();

    // Rostered, but with no shift — which is not the same as unscheduled.
    expect($schedule->shift_id)->toBeNull();
});

it('refuses to roster a shift onto a day it does not run', function (): void {
    // Monday through Friday only (Carbon day numbers, 0 = Sunday).
    $this->pagi->update(['work_days' => [1, 2, 3, 4, 5]]);

    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => $this->alice->id,
            'shift_id' => $this->pagi->id,
            // 2026-08-09 is a Sunday.
            'date' => '2026-08-09',
        ])
        ->assertSessionHasErrors('shift_id');

    expect(ShiftSchedule::forTenant($this->tenant->id)->whereDate('date', '2026-08-09')->count())->toBe(0);
});

it('skips the days a shift does not run when filling in bulk', function (): void {
    $this->pagi->update(['work_days' => [1, 2, 3, 4, 5]]);

    actingAs($this->admin)
        ->post(route('avana.roster.bulk'), [
            'shift_id' => $this->pagi->id,
            'employee_ids' => [$this->alice->id],
            // Monday, plus the Saturday and Sunday around it.
            'dates' => ['2026-08-10', '2026-08-08', '2026-08-09'],
        ])
        ->assertSessionHas('success');

    $dates = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->alice->id)
        ->pluck('date')
        ->map(fn ($date): string => Carbon::parse($date)->toDateString());

    expect($dates)->toContain('2026-08-10');
    expect($dates)->not->toContain('2026-08-08');
    expect($dates)->not->toContain('2026-08-09');
});

it('lets a shift with no declared days run on any day', function (): void {
    expect($this->pagi->work_days)->toBeNull();

    actingAs($this->admin)
        ->post(route('avana.roster.store'), [
            'employee_id' => $this->alice->id,
            'shift_id' => $this->pagi->id,
            'date' => '2026-08-09',
        ])
        ->assertSessionHas('success');
});

it('allows only one roster row per employee per day', function (): void {
    ShiftSchedule::create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id, 'date' => $this->date, 'shift_id' => $this->pagi->id]);

    expect(fn () => ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => $this->date,
        'shift_id' => $this->siang->id,
    ]))->toThrow(QueryException::class);
});
