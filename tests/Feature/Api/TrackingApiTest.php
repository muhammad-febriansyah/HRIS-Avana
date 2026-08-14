<?php

use App\Events\EmployeeLocationUpdated;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLastLocation;
use App\Models\Tenant;
use App\Models\TrackingLocation;
use App\Models\TrackingSession;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');
    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };
    $this->makeSession = function (array $overrides = []): TrackingSession {
        $attendance = Attendance::create([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'branch_id' => $this->employee->branch_id,
            'date' => now()->subDays(random_int(2, 30))->toDateString(),
            'clock_in_at' => now()->subHour(),
            'status' => 'present',
        ]);

        return TrackingSession::create(array_merge([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'attendance_id' => $attendance->id,
            'started_at' => now()->subHour(),
            'status' => TrackingSession::STATUS_ACTIVE,
        ], $overrides));
    };
});

it('returns only the authenticated employee active session', function (): void {
    $session = ($this->makeSession)();

    ($this->auth)()->getJson('/api/v1/me/tracking/active')
        ->assertOk()
        ->assertJsonPath('data.id', $session->id)
        ->assertJsonPath('data.status', 'active');
});

it('accepts a batch idempotently, calculates distance, and updates latest location', function (): void {
    Event::fake([EmployeeLocationUpdated::class]);
    $session = ($this->makeSession)();
    $firstUuid = (string) Str::uuid();
    $payload = [
        'tracking_session_id' => $session->id,
        'locations' => [
            [
                'client_uuid' => $firstUuid,
                'latitude' => -6.2146,
                'longitude' => 106.8451,
                'accuracy' => 8,
                'speed' => 2.4,
                'recorded_at' => now()->subMinutes(2)->toIso8601String(),
            ],
            [
                'client_uuid' => (string) Str::uuid(),
                'latitude' => -6.2155,
                'longitude' => 106.8451,
                'accuracy' => 7,
                'speed' => 2.6,
                'recorded_at' => now()->subMinute()->toIso8601String(),
            ],
        ],
    ];

    ($this->auth)()->postJson('/api/v1/me/tracking/locations', $payload)
        ->assertOk()
        ->assertJsonPath('data.accepted', 2)
        ->assertJsonPath('data.duplicates', 0);

    expect(TrackingLocation::where('tracking_session_id', $session->id)->count())->toBe(2)
        ->and($session->fresh()->total_distance_meters)->toBeGreaterThan(90)
        ->and(EmployeeLastLocation::where('employee_id', $this->employee->id)->first()?->latitude)->toBe('-6.2155000');

    ($this->auth)()->postJson('/api/v1/me/tracking/locations', $payload)
        ->assertOk()
        ->assertJsonPath('data.stored', 0)
        ->assertJsonPath('data.duplicates', 2);

    expect(TrackingLocation::where('tracking_session_id', $session->id)->count())->toBe(2);
    Event::assertDispatched(EmployeeLocationUpdated::class);
});

it('rejects another employee tracking session', function (): void {
    $other = User::where('email', '!=', 'bagus.p@nusantara.co.id')
        ->where('tenant_id', $this->user->tenant_id)
        ->whereHas('employee')
        ->firstOrFail()
        ->employee;
    $attendance = Attendance::create([
        'tenant_id' => $other->tenant_id,
        'employee_id' => $other->id,
        'branch_id' => $other->branch_id,
        'date' => now()->subDays(40)->toDateString(),
        'clock_in_at' => now()->subHour(),
        'status' => 'present',
    ]);
    $session = TrackingSession::create([
        'tenant_id' => $other->tenant_id,
        'employee_id' => $other->id,
        'attendance_id' => $attendance->id,
        'started_at' => now()->subHour(),
        'status' => TrackingSession::STATUS_ACTIVE,
    ]);

    ($this->auth)()->postJson('/api/v1/me/tracking/locations', [
        'tracking_session_id' => $session->id,
        'locations' => [[
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 10,
            'recorded_at' => now()->toIso8601String(),
        ]],
    ])->assertNotFound();
});

it('rejects a tracking session from another tenant', function (): void {
    $tenant = Tenant::create([
        'name' => 'Tenant API Lain',
        'slug' => 'tenant-api-lain',
        'status' => 'active',
        'billing_status' => 'active',
    ]);
    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'employee_number' => 'API-OTHER-1',
        'full_name' => 'Karyawan Tenant API Lain',
        'status' => 'active',
    ]);
    $attendance = Attendance::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'date' => now()->subDays(50)->toDateString(),
        'clock_in_at' => now()->subHour(),
        'status' => 'present',
    ]);
    $session = TrackingSession::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'attendance_id' => $attendance->id,
        'started_at' => now()->subHour(),
        'status' => TrackingSession::STATUS_ACTIVE,
    ]);

    ($this->auth)()->postJson('/api/v1/me/tracking/locations', [
        'tracking_session_id' => $session->id,
        'locations' => [[
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 10,
            'recorded_at' => now()->toIso8601String(),
        ]],
    ])->assertNotFound();
});

it('rejects inactive sessions and invalid coordinates', function (): void {
    $session = ($this->makeSession)(['status' => TrackingSession::STATUS_COMPLETED, 'ended_at' => now()]);

    ($this->auth)()->postJson('/api/v1/me/tracking/locations', [
        'tracking_session_id' => $session->id,
        'locations' => [[
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.2,
            'longitude' => 106.8,
            'accuracy' => 10,
            'recorded_at' => now()->toIso8601String(),
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('tracking_session_id');

    $active = ($this->makeSession)();
    ($this->auth)()->postJson('/api/v1/me/tracking/locations', [
        'tracking_session_id' => $active->id,
        'locations' => [[
            'client_uuid' => (string) Str::uuid(),
            'latitude' => 91,
            'longitude' => 181,
            'accuracy' => 10,
            'recorded_at' => now()->toIso8601String(),
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['locations.0.latitude', 'locations.0.longitude']);
});

it('creates and completes tracking with the attendance clock lifecycle', function (): void {
    Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    $clockIn = ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'latitude' => -6.2146,
        'longitude' => 106.8451,
    ])->assertOk()->assertJsonPath('data.tracking.status', 'active');

    $sessionId = $clockIn->json('data.tracking_session_id');

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'out',
        'latitude' => -6.2146,
        'longitude' => 106.8451,
    ])->assertOk()->assertJsonPath('data.tracking.status', 'completed');

    expect(TrackingSession::findOrFail($sessionId)->status)->toBe(TrackingSession::STATUS_COMPLETED);
});
