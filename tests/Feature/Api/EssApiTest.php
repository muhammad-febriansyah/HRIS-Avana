<?php

use App\Models\Attendance;
use App\Models\AttendanceSelfie;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\MoodCheckin;
use App\Models\Notification;
use App\Models\Reimbursement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    Storage::fake('public');

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
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

it('notifies the employee when they clock in', function (): void {
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk();

    expect(Notification::where('user_id', $user->id)->where('type', 'attendance')->exists())
        ->toBeTrue();
});

it('clocks out with a selfie and stores the attendance photo', function (): void {
    $employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

    // Start from a clean clock-in-only record for today.
    Attendance::where('employee_id', $employee->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    $attendance = Attendance::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'clock_in_at' => now()->subHour(),
        'status' => 'present',
        'branch_id' => $employee->branch_id,
    ]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'out', 'latitude' => -6.2146, 'longitude' => 106.8451,
        'selfie' => UploadedFile::fake()->image('selfie-out.jpg'),
    ])->assertOk()->assertJsonPath('data.next_action', 'done');

    expect(AttendanceSelfie::where('attendance_id', $attendance->id)->count())->toBe(1);
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
    ($this->auth)()->postJson('/api/v1/me/permissions', ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'type' => 'keluar', 'start_time' => '10:00', 'end_time' => '11:00'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/wfh', ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()])->assertCreated();
    ($this->auth)()->getJson('/api/v1/me/announcements')->assertOk()->assertJsonStructure(['data']);
});

it('submits an izin spanning several days, and lists it back as a range', function (): void {
    ($this->auth)()->postJson('/api/v1/me/permissions', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'type' => 'keluar',
        'reason' => 'Urusan keluarga',
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/me/permissions')->assertOk()
        ->assertJsonPath('data.0.start_date', '2026-09-01')
        ->assertJsonPath('data.0.end_date', '2026-09-03')
        ->assertJsonPath('data.0.start_time', null);
});

it('rejects an izin whose end date precedes its start', function (): void {
    ($this->auth)()->postJson('/api/v1/me/permissions', [
        'start_date' => '2026-09-03',
        'end_date' => '2026-09-01',
        'type' => 'keluar',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');
});

it('rejects a clock time on a multi-day izin', function (): void {
    ($this->auth)()->postJson('/api/v1/me/permissions', [
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-03',
        'type' => 'keluar',
        'start_time' => '10:00',
    ])->assertStatus(422)->assertJsonValidationErrors('start_time');
});

it('submits a reimbursement, numbered and routed to the line manager', function (): void {
    ($this->auth)()->postJson('/api/v1/me/reimbursements', [
        'category' => 'transportasi', 'amount' => 85000,
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertCreated();

    $filed = Reimbursement::latest('id')->firstOrFail();

    expect($filed->number)->toStartWith('RMB-')
        ->and($filed->status)->toBe('pending')
        ->and($filed->category)->toBe('transportasi');
});

it('rejects a reimbursement category the module does not use', function (): void {
    ($this->auth)()->postJson('/api/v1/me/reimbursements', [
        'category' => 'transport', 'amount' => 85000,
    ])->assertJsonValidationErrors('category');
});

it('lists payslips and notifications with the expected envelope', function (): void {
    ($this->auth)()->getJson('/api/v1/me/payslips')->assertOk()->assertJsonStructure(['data']);
    ($this->auth)()->getJson('/api/v1/me/notifications')->assertOk()->assertJsonStructure(['data', 'meta' => ['unread']]);
});

it('forbids a non-employee user (admin) from ESS endpoints', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'rina.a@nusantara.co.id', 'password' => 'password'])->json('access_token');
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

it('records and lists a field visit with photos + GPS', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Bandung',
        'client_name' => 'PT Klien',
        'purpose' => 'Meeting',
        'latitude' => -6.9,
        'longitude' => 107.6,
        'photos' => [
            UploadedFile::fake()->image('visit-1.jpg'),
            UploadedFile::fake()->image('visit-2.jpg'),
        ],
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'location', 'photo_urls', 'status']]])
        ->assertJsonCount(2, 'data.0.photo_urls')
        ->assertJsonPath('data.0.visit_date', now()->toDateString());
});

it('files each checklist task with its own before/after evidence', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Toko Mitra Senayan',
        'tasks' => ['Cek ketersediaan stok', 'Dokumentasi visual area toko'],
        'task_notes' => ['Rak SKU baru', 'Etalase depan'],
        'task_before' => [
            UploadedFile::fake()->image('before-1.jpg'),
            UploadedFile::fake()->image('before-2.jpg'),
        ],
        'task_after' => [
            UploadedFile::fake()->image('after-1.jpg'),
            UploadedFile::fake()->image('after-2.jpg'),
        ],
    ])->assertCreated();

    $tasks = ($this->auth)()->getJson('/api/v1/me/field-visits')
        ->assertOk()
        ->json('data.0.tasks');

    expect($tasks)->toHaveCount(2)
        ->and($tasks[0]['title'])->toBe('Cek ketersediaan stok')
        ->and($tasks[0]['photo_note'])->toBe('Rak SKU baru')
        ->and($tasks[0]['before_photo_url'])->not->toBeNull()
        ->and($tasks[0]['after_photo_url'])->not->toBeNull()
        ->and($tasks[1]['photo_note'])->toBe('Etalase depan');
});

it('keeps a checklist task that carries no evidence', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Toko Tanpa Foto',
        'tasks' => ['Wawancara pelanggan'],
    ])->assertCreated();

    $tasks = ($this->auth)()->getJson('/api/v1/me/field-visits')
        ->assertOk()
        ->json('data.0.tasks');

    expect($tasks[0]['before_photo_url'])->toBeNull()
        ->and($tasks[0]['after_photo_url'])->toBeNull()
        ->and($tasks[0]['photo_note'])->toBeNull();
});

it('aligns task evidence by index so a photo-less task does not shift others', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Toko Sparse',
        'tasks' => ['Tugas tanpa foto', 'Tugas dengan foto'],
        // Only the second task (index 1) carries a before photo.
        'task_before' => [1 => UploadedFile::fake()->image('before-2.jpg')],
    ])->assertCreated();

    $tasks = ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()->json('data.0.tasks');

    expect($tasks[0]['before_photo_url'])->toBeNull()
        ->and($tasks[1]['before_photo_url'])->not->toBeNull();
});

it('uploads an after photo for a task from the visit list', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Toko Tanpa After',
        'tasks' => ['Pasang display'],
        'task_before' => [UploadedFile::fake()->image('before.jpg')],
    ])->assertCreated();

    $visit = ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()->json('data.0');
    $task = $visit['tasks'][0];
    expect($task['after_photo_url'])->toBeNull();

    ($this->auth)()->postJson(
        "/api/v1/me/field-visits/{$visit['id']}/tasks/{$task['id']}/after",
        ['after' => UploadedFile::fake()->image('after.jpg')],
    )->assertOk()->assertJsonPath('data.id', $task['id']);

    $updated = ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()->json('data.0.tasks.0');
    expect($updated['after_photo_url'])->not->toBeNull();
});

it('rejects an after upload when the task does not belong to the visit', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Visit A',
        'tasks' => ['Tugas A'],
    ])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Visit B',
        'tasks' => ['Tugas B'],
    ])->assertCreated();

    $list = ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()->json('data');
    $visitA = collect($list)->firstWhere('location', 'Visit A');
    $visitB = collect($list)->firstWhere('location', 'Visit B');
    $taskB = $visitB['tasks'][0];

    // Task B is not on Visit A → the ownership guard must 404.
    ($this->auth)()->postJson(
        "/api/v1/me/field-visits/{$visitA['id']}/tasks/{$taskB['id']}/after",
        ['after' => UploadedFile::fake()->image('after.jpg')],
    )->assertNotFound();
});

it('records a field visit with no photo at all', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Bandung',
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/me/field-visits')->assertOk()
        ->assertJsonPath('data.0.photo_urls', []);
});

it('filters the field-visit list by date', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => '2026-07-20',
        'location' => 'Kunjungan Kemarin',
    ])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => '2026-07-23',
        'location' => 'Kunjungan Hari Ini',
    ])->assertCreated();

    $list = ($this->auth)()->getJson('/api/v1/me/field-visits?date=2026-07-23')
        ->assertOk()
        ->json('data');

    expect($list)->toHaveCount(1)
        ->and($list[0]['location'])->toBe('Kunjungan Hari Ini');
});

it('paginates and searches the field-visit list', function (): void {
    // A unique token scopes every assertion to just these three, so the demo
    // seeder's own visits don't skew the counts.
    foreach (['ZZQA Alpha', 'ZZQA Beta', 'ZZQA Gamma'] as $location) {
        ($this->auth)()->postJson('/api/v1/me/field-visits', [
            'visit_date' => now()->toDateString(),
            'location' => $location,
        ])->assertCreated();
    }

    // A page caps the payload; meta reports the true total.
    ($this->auth)()->getJson('/api/v1/me/field-visits?search=ZZQA&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 2);

    // The next page carries the remainder.
    ($this->auth)()->getJson('/api/v1/me/field-visits?search=ZZQA&per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 2);

    // Search narrows by location (also matches client / purpose).
    ($this->auth)()->getJson('/api/v1/me/field-visits?search=ZZQA Beta')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.location', 'ZZQA Beta');
});

it('rejects more field-visit photos than allowed', function (): void {
    ($this->auth)()->postJson('/api/v1/me/field-visits', [
        'visit_date' => now()->toDateString(),
        'location' => 'Bandung',
        'photos' => array_map(
            fn (int $i) => UploadedFile::fake()->image("v-{$i}.jpg"),
            range(1, 6),
        ),
    ])->assertStatus(422)->assertJsonValidationErrors('photos');
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
    $me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

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

it('syncs a same-day offline clock with a matching face', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => faceVec()]);

    $earlier = now()->startOfDay()->addHours(8);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'latitude' => -6.2146, 'longitude' => 106.8451,
        'face_embedding' => faceVec(),
        'clocked_at' => $earlier->toDateTimeString(),
    ])->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->date->toDateString())->toBe(now()->toDateString());
    expect((float) $att->face_confidence)->toBeGreaterThan(0.9);
});

it('rejects a previous-day clocked_at so past-day fixes go through corrections', function (): void {
    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'latitude' => -6.2146, 'longitude' => 106.8451,
        'clocked_at' => now()->subDay()->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['clocked_at']);
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

it('returns the employee work locations for geofence auto-detect', function (): void {
    ($this->auth)()->getJson('/api/v1/me/work-locations')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'latitude', 'longitude', 'radius']]]);
});

it('returns a dashboard summary', function (): void {
    ($this->auth)()->getJson('/api/v1/me/dashboard')
        ->assertOk()
        ->assertJsonStructure(['data' => ['leave_available', 'work_minutes_month', 'work_hours_month', 'pending_count']]);
});

it('lists colleagues whose birthday is today on the dashboard', function (): void {
    $me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

    $celebrant = Employee::forTenant($me->tenant_id)->where('id', '!=', $me->id)->firstOrFail();
    $celebrant->update(['birth_date' => now()->subYears(30)->toDateString(), 'status' => 'active']);

    Employee::forTenant($me->tenant_id)
        ->whereKeyNot($celebrant->id)
        ->update(['birth_date' => now()->addDay()->subYears(28)->toDateString()]);

    $res = ($this->auth)()->getJson('/api/v1/me/dashboard')->assertOk();

    expect(collect($res->json('data.birthdays'))->pluck('id'))->toContain($celebrant->id);
});

it('caps the dashboard birthday preview but reports the true total', function (): void {
    $me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

    Employee::forTenant($me->tenant_id)->update(['birth_date' => now()->addDay()->toDateString()]);

    foreach (range(1, 20) as $i) {
        Employee::create([
            'tenant_id' => $me->tenant_id,
            'employee_number' => 'ULTAH-'.$i,
            'full_name' => 'Karyawan Ultah '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'employment_status' => 'permanent',
            'status' => 'active',
            'birth_date' => now()->subYears(30)->toDateString(),
        ]);
    }

    $res = ($this->auth)()->getJson('/api/v1/me/dashboard')->assertOk();

    expect($res->json('data.birthdays'))->toHaveCount(12);
    expect($res->json('data.birthdays_total'))->toBe(20);

    // The sheet endpoint still hands back everyone.
    $all = ($this->auth)()->getJson('/api/v1/me/birthdays')->assertOk();
    expect($all->json('data'))->toHaveCount(20);
});

it('never leaks another tenant\'s birthdays to the full birthday list', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Seberang', 'slug' => 'pt-seberang-sheet']);
    Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-7777',
        'full_name' => 'Sheet Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
        'birth_date' => now()->subYears(25)->toDateString(),
    ]);

    $res = ($this->auth)()->getJson('/api/v1/me/birthdays')->assertOk();

    expect(collect($res->json('data'))->pluck('name'))->not->toContain('Sheet Tenant Lain');
});

it('never leaks another tenant\'s birthdays to the dashboard', function (): void {
    $me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;

    $otherTenant = Tenant::create(['name' => 'PT Seberang', 'slug' => 'pt-seberang-ultah']);
    Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-8888',
        'full_name' => 'Ulang Tahun Tenant Lain',
        'employment_status' => 'permanent',
        'status' => 'active',
        'birth_date' => now()->subYears(25)->toDateString(),
    ]);

    $res = ($this->auth)()->getJson('/api/v1/me/dashboard')->assertOk();
    $names = collect($res->json('data.birthdays'))->pluck('name');

    expect($names)->not->toContain('Ulang Tahun Tenant Lain');
    expect(Employee::whereIn('id', collect($res->json('data.birthdays'))->pluck('id'))->pluck('tenant_id')->unique())
        ->not->toContain($otherTenant->id);
});

it('records and reports a daily mood check-in', function (): void {
    ($this->auth)()->getJson('/api/v1/me/mood')->assertOk()->assertJsonPath('data.checked_in', false);

    ($this->auth)()->postJson('/api/v1/me/mood', ['mood' => 'baik'])
        ->assertOk()->assertJsonPath('data.mood', 'baik');

    ($this->auth)()->getJson('/api/v1/me/mood')->assertOk()
        ->assertJsonPath('data.checked_in', true)
        ->assertJsonPath('data.mood', 'baik');
});

it('rejects an invalid mood value', function (): void {
    ($this->auth)()->postJson('/api/v1/me/mood', ['mood' => 'senang_banget'])
        ->assertStatus(422)->assertJsonValidationErrors('mood');
});

it('keeps the first mood of the day and rejects a second submission', function (): void {
    ($this->auth)()->postJson('/api/v1/me/mood', ['mood' => 'baik'])->assertOk();

    ($this->auth)()->postJson('/api/v1/me/mood', ['mood' => 'buruk'])
        ->assertStatus(409)
        ->assertJsonPath('data.mood', 'baik');

    ($this->auth)()->getJson('/api/v1/me/mood')->assertOk()
        ->assertJsonPath('data.mood', 'baik');

    expect(MoodCheckin::whereDate('date', now()->toDateString())->count())->toBe(1);
});
