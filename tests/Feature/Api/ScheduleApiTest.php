<?php

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

    $this->shift = Shift::create([
        'tenant_id' => $this->employee->tenant_id,
        'code' => 'PG', 'name' => 'Pagi',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'late_tolerance_minutes' => 5, 'status' => 'active',
    ]);

    $this->scheduleFor = function (string $date, ?int $shiftId): ShiftSchedule {
        return ShiftSchedule::create([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'shift_id' => $shiftId,
            'date' => $date,
        ]);
    };
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('returns a seven-day week schedule with today marked', function (): void {
    ($this->scheduleFor)(now()->toDateString(), $this->shift->id);

    $res = ($this->auth)()
        ->getJson('/api/v1/me/schedule')
        ->assertOk()
        ->assertJsonCount(7, 'data')
        ->assertJsonStructure([
            'data' => [['date', 'day_label', 'day_short', 'is_today', 'is_scheduled', 'is_off', 'shift_name', 'start', 'end']],
            'meta' => ['week_start', 'week_end'],
        ]);

    $today = collect($res->json('data'))->firstWhere('is_today', true);
    expect($today['shift_name'])->toBe('Pagi');
    expect($today['start'])->toBe('08:00');
    expect($today['is_off'])->toBeFalse();
});

it('marks a scheduled day off', function (): void {
    ($this->scheduleFor)(now()->toDateString(), null);

    $res = ($this->auth)()->getJson('/api/v1/me/schedule')->assertOk();
    $today = collect($res->json('data'))->firstWhere('is_today', true);

    expect($today['is_off'])->toBeTrue();
    expect($today['shift_name'])->toBeNull();
});

it('exposes today shift on the dashboard summary', function (): void {
    ($this->scheduleFor)(now()->toDateString(), $this->shift->id);

    ($this->auth)()
        ->getJson('/api/v1/me/dashboard')
        ->assertOk()
        ->assertJsonPath('data.today_shift.shift_name', 'Pagi')
        ->assertJsonPath('data.today_shift.start', '08:00');
});

it('flags a late clock-in against the shift start', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00'));
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null, 'status' => 'absent']);
    ($this->scheduleFor)(now()->toDateString(), $this->shift->id);

    // Log in at the frozen time so the JWT is valid, then clock in.
    $this->app['auth']->forgetGuards();
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id', 'password' => 'password',
    ])->json('access_token');
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/me/attendance/clock', [
            'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        ])->assertOk();

    $att = Attendance::where('employee_id', $this->employee->id)
        ->whereNotNull('clock_in_at')->latest('id')->firstOrFail();

    expect($att->status)->toBe('late');
    expect($att->late_minutes)->toBe(60);
    expect($att->shift_id)->toBe($this->shift->id);
});

it('stays present when clocking in within tolerance', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-15 08:03:00'));
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null, 'status' => 'absent']);
    ($this->scheduleFor)(now()->toDateString(), $this->shift->id);

    $this->app['auth']->forgetGuards();
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id', 'password' => 'password',
    ])->json('access_token');
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/me/attendance/clock', [
            'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        ])->assertOk();

    $att = Attendance::where('employee_id', $this->employee->id)
        ->whereNotNull('clock_in_at')->latest('id')->firstOrFail();

    expect($att->status)->toBe('present');
    expect($att->late_minutes)->toBe(0);
});
