<?php

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    // Logging in on demand, so moving the clock forward between punches does
    // not expire the token out from under the test.
    $this->auth = function () {
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'bagus.p@nusantara.co.id',
            'password' => 'password',
        ])->json('access_token');
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };

    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

    $this->malam = Shift::create([
        'tenant_id' => $this->employee->tenant_id,
        'code' => 'MALAM-T', 'name' => 'Shift Malam',
        'start_time' => '22:00:00', 'end_time' => '06:00:00',
        'late_tolerance_minutes' => 10, 'status' => 'active',
    ]);

    $this->night = '2026-08-10';

    $this->rosterNight = function (): void {
        ShiftSchedule::create([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->malam->id,
            'date' => $this->night,
        ]);
    };

    $this->punch = function (string $at, string $type) {
        Carbon::setTestNow(Carbon::parse($at));

        return ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
            'type' => $type,
            'latitude' => -6.2146,
            'longitude' => 106.8451,
        ]);
    };
});

afterEach(fn () => Carbon::setTestNow());

it('lets a night shift worker clock out after midnight', function (): void {
    ($this->rosterNight)();

    ($this->punch)($this->night.' 22:02:00', 'in')->assertOk();
    ($this->punch)('2026-08-11 06:05:00', 'out')->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', $this->night)
        ->firstOrFail();

    // One shift on the night it started, not two half days.
    expect($attendance->clock_out_at)->not->toBeNull();
    expect((int) $attendance->work_minutes)->toBe(483);
    expect(Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-08-11')->count())->toBe(0);
});

it('measures a small-hours arrival against the shift that started last night', function (): void {
    ($this->rosterNight)();

    ($this->punch)('2026-08-11 00:30:00', 'in')->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', $this->night)
        ->firstOrFail();

    // 00:30 against a 22:00 start is two and a half hours late, not early.
    expect($attendance->status)->toBe('late');
    expect((int) $attendance->late_minutes)->toBe(150);
    expect((int) $attendance->shift_id)->toBe($this->malam->id);
});

it('still books a day shift on its own date', function (): void {
    $pagi = Shift::create([
        'tenant_id' => $this->employee->tenant_id,
        'code' => 'PAGI-T', 'name' => 'Pagi',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'late_tolerance_minutes' => 10, 'status' => 'active',
    ]);

    ShiftSchedule::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'shift_id' => $pagi->id,
        'date' => $this->night,
    ]);

    ($this->punch)($this->night.' 08:03:00', 'in')->assertOk();
    ($this->punch)($this->night.' 17:10:00', 'out')->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', $this->night)
        ->firstOrFail();

    expect($attendance->status)->toBe('present');
    expect($attendance->clock_out_at)->not->toBeNull();
});

it('closes an open clock-in when the worker overruns the shift end', function (): void {
    ($this->rosterNight)();

    ($this->punch)($this->night.' 22:02:00', 'in')->assertOk();

    // Two hours past the 06:00 end — outside the night window, but the shift
    // is plainly still the one being closed.
    ($this->punch)('2026-08-11 08:00:00', 'out')->assertOk();

    expect(Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', $this->night)
        ->firstOrFail()
        ->clock_out_at)->not->toBeNull();
});

it('reports the night in progress as today for the app', function (): void {
    ($this->rosterNight)();

    ($this->punch)($this->night.' 22:02:00', 'in')->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-08-11 02:00:00'));

    ($this->auth)()
        ->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        // Mid-shift at 02:00 the app must not show them as not yet clocked in.
        ->assertJsonPath('data.clock_in', '22:02');
});

it('does not reach back to a night shift the employee did not work', function (): void {
    // Rostered off the night before, so a morning punch belongs to its own day.
    ShiftSchedule::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'shift_id' => null,
        'date' => $this->night,
    ]);

    ($this->punch)('2026-08-11 05:30:00', 'in')->assertOk();

    expect(Attendance::where('employee_id', $this->employee->id)->whereDate('date', '2026-08-11')->count())->toBe(1);
    expect(Attendance::where('employee_id', $this->employee->id)->whereDate('date', $this->night)->whereNotNull('clock_in_at')->count())->toBe(0);
});
