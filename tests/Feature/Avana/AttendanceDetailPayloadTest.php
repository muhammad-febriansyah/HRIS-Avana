<?php

use App\Models\Attendance;
use App\Models\AttendanceSelfie;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
});

/**
 * Build an attendance with the given selfie capture times.
 *
 * @param  list<string>  $selfieTimes
 */
function attendanceWithSelfies(int $tenantId, int $employeeId, array $selfieTimes, ?string $clockOutAt): Attendance
{
    $attendance = Attendance::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employeeId,
        'date' => '2026-08-10',
        'clock_in_at' => '2026-08-10 08:00:00',
        'clock_in_lat' => -6.44567,
        'clock_in_lng' => 106.85184,
        'clock_out_at' => $clockOutAt,
        'status' => 'present',
        'location_status' => 'wfa',
    ]);

    foreach ($selfieTimes as $index => $capturedAt) {
        AttendanceSelfie::create([
            'tenant_id' => $tenantId,
            'attendance_id' => $attendance->id,
            'employee_id' => $employeeId,
            'file_path' => "selfies/shot-{$index}.jpg",
            'captured_at' => $capturedAt,
        ]);
    }

    return $attendance;
}

it('shows the last selfie on the clock-out card', function (): void {
    $attendance = attendanceWithSelfies(
        (int) $this->employee->tenant_id,
        (int) $this->employee->id,
        ['2026-08-10 08:00:00', '2026-08-10 17:00:00'],
        '2026-08-10 17:00:00',
    );

    actingAs($this->admin)
        ->get(route('avana.absensi.show', $attendance))
        ->assertInertia(function ($page): void {
            $inPhoto = $page->toArray()['props']['attendance']['clock_in']['photo_url'];
            $outPhoto = $page->toArray()['props']['attendance']['clock_out']['photo_url'];

            expect($inPhoto)->toContain('shot-0');
            expect($outPhoto)->toContain('shot-1');
        });
});

it('leaves the clock-out photo empty when only the clock-in selfie exists', function (): void {
    $attendance = attendanceWithSelfies(
        (int) $this->employee->tenant_id,
        (int) $this->employee->id,
        ['2026-08-10 08:00:00'],
        null,
    );

    actingAs($this->admin)
        ->get(route('avana.absensi.show', $attendance))
        ->assertInertia(fn ($page) => $page->where('attendance.clock_out.photo_url', null));
});

it('passes the raw location status through instead of assuming a geofence', function (): void {
    $attendance = attendanceWithSelfies(
        (int) $this->employee->tenant_id,
        (int) $this->employee->id,
        [],
        null,
    );

    actingAs($this->admin)
        ->get(route('avana.absensi.show', $attendance))
        ->assertInertia(fn ($page) => $page
            ->where('attendance.location_status', 'wfa')
            ->where('attendance.work_location', null)
            ->etc());
});
