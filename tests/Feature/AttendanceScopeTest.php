<?php

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\Branch;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\AvanaDemoSeeder;

/** Far from the seeded Jakarta office (-6.2146, 106.8451) — Bandung. */
const AWAY_LAT = -6.9;
const AWAY_LNG = 107.6;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;

    // A second office in a different branch, right where the employee will
    // clock in from — reachable only once the scope widens past 'assigned'.
    $otherBranch = Branch::create([
        'tenant_id' => $this->employee->tenant_id,
        'name' => 'Bandung',
        'code' => 'BDG-TEST',
    ]);

    $this->otherOffice = WorkLocation::create([
        'tenant_id' => $this->employee->tenant_id,
        'branch_id' => $otherBranch->id,
        'name' => 'Kantor Bandung',
        'latitude' => AWAY_LAT,
        'longitude' => AWAY_LNG,
        'radius_meter' => 200,
        'status' => 'active',
    ]);

    Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->clockInAway = fn (array $extra = []) => ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => AWAY_LAT, 'longitude' => AWAY_LNG,
    ] + $extra);
});

/** Set the tenant-wide default scope. */
function setPolicyScope(int $tenantId, string $scope): void
{
    $policy = AttendancePolicy::resolve($tenantId);
    $policy->attendance_scope = $scope;
    $policy->save();
}

it('refuses a clock-in at another branch office by default', function (): void {
    ($this->clockInAway)()
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'di luar area'));
});

it('accepts a clock-in at any branch office when the policy allows it', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANY_BRANCH);

    ($this->clockInAway)()->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->firstOrFail();

    // Credited to the office actually stood in, not the assigned one.
    expect($attendance->work_location_id)->toBe($this->otherOffice->id)
        ->and($attendance->location_status)->toBe('inside');
});

it('accepts a WFA clock-in far from every office, and records the point', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANYWHERE);

    // The middle of nowhere: not near any seeded office.
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -8.65, 'longitude' => 115.21,
    ])->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->firstOrFail();

    expect($attendance->location_status)->toBe('wfa')
        ->and($attendance->work_location_id)->toBeNull()
        ->and((float) $attendance->clock_in_lat)->toBe(-8.65)
        ->and((float) $attendance->clock_in_lng)->toBe(115.21);
});

it('still requires GPS under WFA', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANYWHERE);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in'])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'GPS'));
});

it('still blocks a faked GPS location under WFA', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANYWHERE);

    ($this->clockInAway)(['is_mock_location' => true])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Fake GPS'));
});

it('lets an employee override a stricter tenant default', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ASSIGNED);

    $this->employee->update(['attendance_scope' => AttendancePolicy::SCOPE_ANYWHERE]);

    ($this->clockInAway)()->assertOk();
});

it('lets an employee be held to a stricter scope than the tenant default', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANYWHERE);

    $this->employee->update(['attendance_scope' => AttendancePolicy::SCOPE_ASSIGNED]);

    ($this->clockInAway)()->assertStatus(422);
});

it('falls back to the strictest scope when the stored value is unrecognised', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANYWHERE);

    // A bad value must never silently open the geofence.
    $this->employee->update(['attendance_scope' => 'garbage']);

    ($this->clockInAway)()->assertStatus(422);
});

it('reports the scope alongside the work locations', function (): void {
    setPolicyScope($this->employee->tenant_id, AttendancePolicy::SCOPE_ANY_BRANCH);

    ($this->auth)()->getJson('/api/v1/me/work-locations')
        ->assertOk()
        ->assertJsonPath('scope', AttendancePolicy::SCOPE_ANY_BRANCH)
        // Both offices are reachable now, not just the assigned one.
        ->assertJsonCount(2, 'data');
});
