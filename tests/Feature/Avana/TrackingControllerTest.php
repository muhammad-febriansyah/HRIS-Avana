<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLastLocation;
use App\Models\Tenant;
use App\Models\TrackingLocation;
use App\Models\TrackingSession;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = Employee::forTenant($this->admin->tenant_id)->firstOrFail();
    $this->makeTrackingSession = function (array $overrides = []): TrackingSession {
        $attendance = Attendance::create([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'branch_id' => $this->employee->branch_id,
            'date' => '2026-08-14',
            'clock_in_at' => '2026-08-14 08:00:00',
            'status' => 'present',
        ]);

        return TrackingSession::create(array_merge([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'attendance_id' => $attendance->id,
            'started_at' => '2026-08-14 08:00:00',
            'last_location_at' => '2026-08-14 09:00:00',
            'status' => TrackingSession::STATUS_ACTIVE,
        ], $overrides));
    };
});

it('renders tenant scoped live tracking employees with a recent trail', function (): void {
    $session = ($this->makeTrackingSession)();
    EmployeeLastLocation::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'tracking_session_id' => $session->id,
        'latitude' => -6.2146,
        'longitude' => 106.8451,
        'accuracy' => 8,
        'recorded_at' => now(),
    ]);
    foreach (range(1, 3) as $index) {
        TrackingLocation::create([
            'tracking_session_id' => $session->id,
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.2146 - ($index / 1000),
            'longitude' => 106.8451,
            'accuracy' => 8,
            'is_accepted' => true,
            'recorded_at' => now()->subMinutes(30 - $index),
        ]);
    }

    actingAs($this->admin)
        ->get(route('avana.tracking.live'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/tracking/live', false)
            ->has('employees', 1)
            ->where('employees.0.employee_id', $this->employee->id)
            ->where('employees.0.latitude', -6.2146)
            ->where('employees.0.status', 'active')
            ->has('employees.0.trail', 3)
            ->has('departments')
            ->has('shifts'));
});

it('renders history and a sampled route detail', function (): void {
    $session = ($this->makeTrackingSession)([
        'status' => TrackingSession::STATUS_COMPLETED,
        'ended_at' => '2026-08-14 17:00:00',
        'total_duration_seconds' => 32400,
        'total_distance_meters' => 8400,
    ]);

    foreach (range(1, 3) as $index) {
        TrackingLocation::create([
            'tracking_session_id' => $session->id,
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'client_uuid' => (string) Str::uuid(),
            'latitude' => -6.2146 - ($index / 1000),
            'longitude' => 106.8451,
            'accuracy' => 8,
            'is_accepted' => true,
            'recorded_at' => "2026-08-14 08:0{$index}:00",
        ]);
    }

    actingAs($this->admin)
        ->get(route('avana.tracking.history', ['date' => '2026-08-14']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/tracking/history', false)
            ->has('sessions.data', 1)
            ->where('sessions.data.0.points_count', 3));

    actingAs($this->admin)
        ->get(route('avana.tracking.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/tracking/show', false)
            ->where('session.id', $session->id)
            ->where('session.points_count', 3)
            ->has('points', 3)
            ->where('sampled', false));
});

it('does not expose another tenant tracking session', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'Tenant Rahasia',
        'slug' => 'tenant-rahasia',
        'company_name' => 'PT Tenant Rahasia',
        'status' => 'active',
        'billing_status' => 'active',
    ]);
    $otherEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'SECRET-001',
        'full_name' => 'Karyawan Tenant Lain',
        'status' => 'active',
    ]);
    $attendance = Attendance::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $otherEmployee->id,
        'date' => '2026-08-14',
        'clock_in_at' => '2026-08-14 08:00:00',
        'status' => 'present',
    ]);
    $session = TrackingSession::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $otherEmployee->id,
        'attendance_id' => $attendance->id,
        'started_at' => '2026-08-14 08:00:00',
        'status' => TrackingSession::STATUS_ACTIVE,
    ]);

    actingAs($this->admin)
        ->get(route('avana.tracking.show', $session))
        ->assertNotFound();
});
