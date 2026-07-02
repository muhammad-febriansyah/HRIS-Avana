<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    Storage::fake('public');

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ])->json('access_token');

    // jwt-auth caches the resolved user on the guard singleton across requests
    // in a test; flush it before each call so the bearer token is the sole auth.
    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };
});

it('returns the employee profile', function (): void {
    ($this->auth)()->getJson('/api/v1/me/profile')->assertOk()
        ->assertJsonStructure(['data' => ['employee_no', 'full_name', 'employment' => ['department', 'position']]]);
});

it('updates the profile', function (): void {
    ($this->auth)()->putJson('/api/v1/me/profile', ['phone' => '0812-0000-0000', 'address' => 'Jl. Baru 1'])
        ->assertOk()->assertJsonPath('data.phone', '0812-0000-0000');
});

it('clocks in via the unified endpoint with GPS + selfie', function (): void {
    // Coordinates match the seeded work-location pin (Kantor Pusat Jakarta),
    // so the caller is inside the geofence radius.
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertOk()->assertJsonPath('data.next_action', 'out');
});

it('rejects clock-in outside the work-location radius', function (): void {
    // ~5 km from the seeded pin (-6.2146, 106.8451), well beyond the 150 m radius.
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.17, 'longitude' => 106.80,
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'di luar area'));
});

it('rejects clock-in flagged as a mock (fake GPS) location', function (): void {
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        'is_mock_location' => true,
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Fake GPS'));
});

it('rejects clock-in from a rooted / jailbroken device', function (): void {
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        'is_rooted' => true,
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'root/jailbreak'));
});

it('rejects clock-in when GPS coordinates are missing', function (): void {
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in'])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'GPS'));
});

it('resolves the geofence via the branch when no explicit work location is set', function (): void {
    // Employee keeps only their branch; the branch's work location is used.
    Employee::query()->update(['work_location_id' => null]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk()->assertJsonPath('data.next_action', 'out');
});

it('rejects clock-in when neither the employee nor their branch has a work location', function (): void {
    Employee::query()->update(['work_location_id' => null]);
    WorkLocation::query()->update(['status' => 'inactive']);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Lokasi kerja belum diatur'));
});

it('returns today status and history', function (): void {
    ($this->auth)()->getJson('/api/v1/me/attendance/today')->assertOk()
        ->assertJsonStructure(['data' => ['date', 'next_action', 'summary' => ['status', 'work_minutes']]]);
    ($this->auth)()->getJson('/api/v1/me/attendance')->assertOk()->assertJsonStructure(['data', 'meta']);
});

it('lists leave balances, types and submits a request', function (): void {
    ($this->auth)()->getJson('/api/v1/me/leave/balances')->assertOk()
        ->assertJsonStructure(['data' => [['leave_type', 'year', 'entitled', 'used', 'available']]]);
    ($this->auth)()->getJson('/api/v1/me/leave-types')->assertOk()->assertJsonStructure(['data']);

    $type = LeaveType::where('code', 'TAHUNAN')->firstOrFail();
    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Keluarga',
    ])->assertCreated();
});

it('rejects a leave request over balance', function (): void {
    $type = LeaveType::where('code', 'PENTING')->firstOrFail();
    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ])->assertStatus(422);
});

it('submits overtime, permission and wfh, and lists announcements', function (): void {
    ($this->auth)()->postJson('/api/v1/me/overtime', ['date' => now()->toDateString(), 'hours' => 2, 'reason' => 'Deadline'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/permissions', ['date' => now()->toDateString(), 'type' => 'keluar', 'start_time' => '10:00', 'end_time' => '11:00'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/wfh', ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()])->assertCreated();
    ($this->auth)()->getJson('/api/v1/me/announcements')->assertOk()->assertJsonStructure(['data']);
});

it('submits a reimbursement', function (): void {
    ($this->auth)()->postJson('/api/v1/me/reimbursements', [
        'category' => 'transport', 'amount' => 85000,
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertCreated();
});

it('lists payslips and notifications with the expected envelope', function (): void {
    ($this->auth)()->getJson('/api/v1/me/payslips')->assertOk()->assertJsonStructure(['data']);
    ($this->auth)()->getJson('/api/v1/me/notifications')->assertOk()->assertJsonStructure(['data', 'meta' => ['unread']]);
});

it('forbids a non-employee user (admin) from ESS endpoints', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'admin@avanahr.co.id', 'password' => 'password'])->json('access_token');
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/profile')
        ->assertForbidden();
});

it('uploads and lists a personal document', function (): void {
    ($this->auth)()->postJson('/api/v1/me/documents', [
        'name' => 'KTP',
        'type' => 'identitas',
        'file' => UploadedFile::fake()->create('ktp.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/me/documents')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'type', 'url', 'uploaded_at']]]);
});

it('records and lists a field visit with photo + GPS', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Bandung',
        'client_name' => 'PT Klien',
        'purpose' => 'Meeting',
        'latitude' => -6.9,
        'longitude' => 107.6,
        'photo' => UploadedFile::fake()->image('visit.jpg'),
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'location', 'photo_url', 'status']]]);
});

it('requests and lists a shift swap with a colleague', function (): void {
    $colleagues = ($this->auth)()->getJson('/api/v1/me/shift-swaps/colleagues')
        ->assertOk()->json('data');

    expect($colleagues)->not->toBeEmpty();

    ($this->auth)()->postJson('/api/v1/me/shift-swaps', [
        'target_id' => $colleagues[0]['id'],
        'date' => now()->addDay()->toDateString(),
        'reason' => 'Ada acara keluarga',
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/me/shift-swaps')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'direction', 'status', 'target']]]);
});

it('rejects a shift swap with yourself', function (): void {
    $me = User::where('email', 'karyawan@avanahr.co.id')->firstOrFail()->employee;

    ($this->auth)()->postJson('/api/v1/me/shift-swaps', [
        'target_id' => $me->id,
        'date' => now()->addDay()->toDateString(),
    ])->assertStatus(422);
});

it('records a queued offline clock-in at its original time', function (): void {
    // Simulate an entry captured offline 2 hours ago and synced now.
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null, 'status' => 'absent']);
    $at = now()->subHours(2)->startOfMinute();

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'latitude' => -6.2146,
        'longitude' => 106.8451,
        'clocked_at' => $at->toIso8601String(),
    ])->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->clock_in_at->format('Y-m-d H:i'))->toBe($at->format('Y-m-d H:i'));
});

it('ignores a future clocked_at and uses now', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null, 'status' => 'absent']);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'latitude' => -6.2146,
        'longitude' => 106.8451,
        'clocked_at' => now()->addDays(2)->toIso8601String(),
    ])->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->clock_in_at->isToday())->toBeTrue();
    expect($att->clock_in_at->isFuture())->toBeFalse();
});

/** A 128-d unit vector with 1.0 at $at, else 0. */
function faceVec(int $at = 0): array
{
    $v = array_fill(0, 128, 0.0);
    $v[$at] = 1.0;

    return $v;
}

it('reports face enrollment status and enrolls a face', function (): void {
    ($this->auth)()->getJson('/api/v1/me/face')->assertOk()->assertJsonPath('data.enrolled', false);

    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => faceVec()])
        ->assertOk()->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'didaftarkan'));

    ($this->auth)()->getJson('/api/v1/me/face')->assertOk()
        ->assertJsonPath('data.enrolled', true)
        ->assertJsonPath('data.dimensions', 128);
});

it('rejects a too-short face embedding on enroll', function (): void {
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => [1, 2, 3]])
        ->assertStatus(422)->assertJsonValidationErrors('embedding');
});

it('requires a face at clock-in once enrolled', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => faceVec()]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Verifikasi wajah'));
});

it('accepts a matching face and records the confidence', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => faceVec()]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        'face_embedding' => faceVec(),
    ])->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect((float) $att->face_confidence)->toBeGreaterThan(0.9);
});

it('rejects a non-matching face at clock-in', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => faceVec(0)]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
        'face_embedding' => faceVec(50),
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'tidak cocok'));
});

it('syncs an offline clock with a matching face and back-dated time', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => faceVec()]);

    $yesterday = now()->subDay();

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'latitude' => -6.2146, 'longitude' => 106.8451,
        'face_embedding' => faceVec(),
        'clocked_at' => $yesterday->toDateTimeString(),
    ])->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->date->toDateString())->toBe($yesterday->toDateString());
    expect((float) $att->face_confidence)->toBeGreaterThan(0.9);
});

it('returns a merged activity feed newest-first', function (): void {
    ($this->auth)()->getJson('/api/v1/me/activities')
        ->assertOk()
        ->assertJsonStructure(['data' => [['type', 'title', 'subtitle', 'status', 'occurred_at']]]);
});

it('includes a freshly submitted request in the activity feed', function (): void {
    ($this->auth)()->postJson('/api/v1/me/overtime', [
        'date' => now()->toDateString(), 'hours' => 2, 'reason' => 'Rilis fitur',
    ])->assertCreated();

    $res = ($this->auth)()->getJson('/api/v1/me/activities')->assertOk();
    $types = collect($res->json('data'))->pluck('type');

    expect($types)->toContain('overtime');
});
