<?php

use App\Models\Attendance;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantTime;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

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
    $this->tenant = Tenant::findOrFail($this->employee->tenant_id);

    $this->pagi = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PAGI-TZ', 'name' => 'Pagi',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'late_tolerance_minutes' => 5, 'status' => 'active',
    ]);

    $this->setZone = function (string $zone): void {
        $this->tenant->update(['timezone' => $zone]);
        TenantTime::forget();
    };

    $this->rosterOn = function (string $date): void {
        ShiftSchedule::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee->id,
            'shift_id' => $this->pagi->id,
            'date' => $date,
        ]);
    };
});

afterEach(function (): void {
    Carbon::setTestNow();
    TenantTime::forget();
});

it('defaults a tenant to WIB', function (): void {
    expect(TenantTime::zone($this->tenant->id))->toBe('Asia/Jakarta');
    expect(TenantTime::shortLabel(TenantTime::zone($this->tenant->id)))->toBe('WIB');
});

it('reads a Makassar clock-in as the hour Makassar saw', function (): void {
    ($this->setZone)('Asia/Makassar');
    ($this->rosterOn)('2026-08-10');

    // 08:03 in Makassar is one absolute instant; in Jakarta it reads 07:03.
    Carbon::setTestNow(Carbon::parse('2026-08-10 08:03:00', 'Asia/Makassar'));

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk();

    ($this->auth)()
        ->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        ->assertJsonPath('data.clock_in', '08:03')
        ->assertJsonPath('data.date', '2026-08-10')
        ->assertJsonPath('requirements.timezone', 'Asia/Makassar')
        ->assertJsonPath('requirements.timezone_label', 'WITA');
});

it('does not mark a Makassar arrival late for its own eight o clock', function (): void {
    ($this->setZone)('Asia/Makassar');
    ($this->rosterOn)('2026-08-10');

    Carbon::setTestNow(Carbon::parse('2026-08-10 08:03:00', 'Asia/Makassar'));

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-08-10')
        ->firstOrFail();

    // Under one global WIB clock this read as 07:03 and was silently "early";
    // the same punch an hour later would have been judged wrongly too.
    expect($attendance->status)->toBe('present');
    expect((int) $attendance->late_minutes)->toBe(0);
});

it('marks a Makassar arrival late once it passes the local tolerance', function (): void {
    ($this->setZone)('Asia/Makassar');
    ($this->rosterOn)('2026-08-10');

    Carbon::setTestNow(Carbon::parse('2026-08-10 08:30:00', 'Asia/Makassar'));

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk();

    $attendance = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-08-10')
        ->firstOrFail();

    expect($attendance->status)->toBe('late');
    expect((int) $attendance->late_minutes)->toBe(30);
});

it('books a Jayapura punch on the day Jayapura is having', function (): void {
    ($this->setZone)('Asia/Jayapura');

    // 00:30 on the 11th in Jayapura is still the 10th in Jakarta.
    Carbon::setTestNow(Carbon::parse('2026-08-11 00:30:00', 'Asia/Jayapura'));

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk();

    expect(Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-08-11')
        ->whereNotNull('clock_in_at')
        ->count())->toBe(1);
});

it('lets a branch keep its own clock inside a WIB company', function (): void {
    ($this->setZone)('Asia/Jakarta');

    $this->employee->branch->update(['timezone' => 'Asia/Makassar']);
    TenantTime::forget();

    expect(TenantTime::zoneForBranch($this->tenant->id, $this->employee->branch_id))
        ->toBe('Asia/Makassar');

    // The company itself is unchanged.
    expect(TenantTime::zone($this->tenant->id))->toBe('Asia/Jakarta');
});

it('falls back to the company clock when a branch names none', function (): void {
    ($this->setZone)('Asia/Jayapura');

    $this->employee->branch->update(['timezone' => null]);
    TenantTime::forget();

    expect(TenantTime::zoneForBranch($this->tenant->id, $this->employee->branch_id))
        ->toBe('Asia/Jayapura');
});

it('refuses a zone outside the three Indonesian ones', function (): void {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $this->actingAs($admin)
        ->put('/avana/perusahaan/profile', [
            'name' => 'PT Nusantara Jaya',
            'timezone' => 'Europe/London',
        ])
        ->assertSessionHasErrors('timezone');
});

it('saves the zone the tenant admin picks', function (): void {
    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $this->actingAs($admin)
        ->put('/avana/perusahaan/profile', [
            'name' => 'PT Nusantara Jaya',
            'timezone' => 'Asia/Makassar',
        ])
        ->assertRedirect();

    TenantTime::forget();

    expect(TenantTime::zone($this->tenant->id))->toBe('Asia/Makassar');
});
