<?php

use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AttendanceFines;
use App\Support\Roster;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;

/** A 07:00 shift with no grace period, like Prihata's Shift Pagi Inspektor. */
function strictShift(?int $tolerance = 0): Shift
{
    return new Shift([
        'start_time' => '07:00:00',
        'end_time' => '17:00:00',
        'late_tolerance_minutes' => $tolerance,
    ]);
}

it('judges a punch to the minute it is shown in', function (string $clockedAt, string $status, int $lateMinutes): void {
    $verdict = Roster::evaluate(strictShift(), Carbon::parse('2026-11-13 '.$clockedAt), '2026-11-13');

    expect($verdict['status'])->toBe($status)
        ->and($verdict['late_minutes'])->toBe($lateMinutes);
})->with([
    'early' => ['06:58:12', 'present', 0],
    'on the minute' => ['07:00:00', 'present', 0],
    'seconds past, same minute' => ['07:00:40', 'present', 0],
    'next minute' => ['07:01:05', 'late', 1],
    'well late' => ['07:25:59', 'late', 25],
]);

it('still honours the shift tolerance', function (): void {
    $shift = strictShift(10);

    expect(Roster::evaluate($shift, Carbon::parse('2026-11-13 07:10:59'), '2026-11-13')['status'])->toBe('present')
        ->and(Roster::evaluate($shift, Carbon::parse('2026-11-13 07:11:00'), '2026-11-13'))
        ->toMatchArray(['status' => 'late', 'late_minutes' => 11]);
});

it('re-judges stored sub-minute late rows as on time and drops their automatic penalty', function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $tenant = Tenant::findOrFail($admin->tenant_id);
    $employee = Employee::forTenant($tenant->id)->orderBy('id')->firstOrFail();

    Attendance::where('employee_id', $employee->id)->whereDate('date', '>=', '2026-11-10')->delete();

    $subMinute = Attendance::create([
        'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => '2026-11-12',
        'clock_in_at' => '2026-11-12 07:00:40', 'status' => 'late', 'late_minutes' => 0,
    ]);
    $reallyLate = Attendance::create([
        'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'date' => '2026-11-11',
        'clock_in_at' => '2026-11-11 07:20:00', 'status' => 'late', 'late_minutes' => 20,
    ]);

    AttendanceFines::generate($tenant->id, '2026-11-11', '2026-11-12', $employee->id);
    $penaltyOn = fn (string $date) => AttendancePenalty::where('employee_id', $employee->id)
        ->where('violation_type', 'late')->whereDate('date', $date)->exists();
    expect($penaltyOn('2026-11-12'))->toBeTrue();

    $this->travelTo(Carbon::parse('2026-11-13 10:00'));
    artisan('avana:reconcile-attendance-lateness', ['--tenant' => $tenant->id, '--from' => '2026-11-01'])
        ->assertSuccessful();

    expect($subMinute->fresh()->status)->toBe('present')
        ->and($reallyLate->fresh()->status)->toBe('late');

    AttendanceFines::generate($tenant->id, '2026-11-11', '2026-11-12', $employee->id);

    expect($penaltyOn('2026-11-12'))->toBeFalse()
        ->and($penaltyOn('2026-11-11'))->toBeTrue();
});
