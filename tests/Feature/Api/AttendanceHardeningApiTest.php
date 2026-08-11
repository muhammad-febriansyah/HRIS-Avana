<?php

use App\Models\Attendance;
use App\Models\AttendanceChallenge;
use App\Models\AttendancePolicy;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
    $this->tenantId = $this->employee->tenant_id;
    config([
        'services.face_recognition.url' => 'http://face-service.test',
        'services.face_recognition.api_key' => 'test-face-key',
        'services.face_recognition.model_version' => 'sface-2021dec-v1',
    ]);

    Attendance::query()->update(['clock_in_at' => null, 'clock_out_at' => null]);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->setPolicy = function (array $attrs): void {
        AttendancePolicy::updateOrCreate(['tenant_id' => $this->tenantId], $attrs);
    };

    // Inside the seeded work-location geofence (Kantor Pusat Jakarta).
    $this->at = ['latitude' => -6.2146, 'longitude' => 106.8451];
});

/** @return list<UploadedFile> */
function hardeningFaceImages(): array
{
    return collect(range(1, 3))
        ->map(fn (int $index): UploadedFile => UploadedFile::fake()->image("hardening-face-{$index}.jpg", 480, 640))
        ->all();
}

/** @return array<string, mixed> */
function hardeningEnrollmentResponse(): array
{
    $embedding = array_fill(0, 128, 0.0);
    $embedding[0] = 1.0;

    return [
        'embedding' => $embedding,
        'model_version' => 'sface-2021dec-v1',
        'dimensions' => 128,
        'individual_similarities' => [0.91, 0.92, 0.93],
    ];
}

it('issues a single-use liveness challenge', function (): void {
    ($this->auth)()->postJson('/api/v1/me/attendance/challenge')
        ->assertOk()
        ->assertJsonStructure(['data' => ['nonce', 'expires_at']])
        ->assertJsonPath('data.nonce', fn (string $n): bool => strlen($n) > 10);
});

it('exposes verification requirements in today status', function (): void {
    ($this->setPolicy)(['require_face_enrollment' => true, 'require_liveness_challenge' => true]);

    ($this->auth)()->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        ->assertJsonPath('requirements.require_face_enrollment', true)
        ->assertJsonPath('requirements.require_liveness_challenge', true)
        ->assertJsonPath('requirements.face_enrolled', false);
});

it('requires a valid nonce when the liveness challenge is enforced', function (): void {
    ($this->setPolicy)(['require_liveness_challenge' => true]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', ...$this->at])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'verifikasi'));
});

it('accepts a fresh nonce and rejects its replay', function (): void {
    ($this->setPolicy)(['require_liveness_challenge' => true]);

    $nonce = ($this->auth)()->postJson('/api/v1/me/attendance/challenge')->json('data.nonce');

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', 'nonce' => $nonce, ...$this->at])
        ->assertOk();

    expect(AttendanceChallenge::where('nonce', $nonce)->first()->used_at)->not->toBeNull();

    // Replaying the same nonce is rejected before any punch logic runs.
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', 'nonce' => $nonce, ...$this->at])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'dipakai') || str_contains($m, 'kedaluwarsa'));
});

it('blocks clock-in when face enrollment is mandatory but the employee has none', function (): void {
    ($this->setPolicy)(['require_face_enrollment' => true]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', ...$this->at])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'wajib mendaftarkan'));
});

it('blocks an emulator under the default (strict) policy', function (): void {
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', 'is_emulator' => true, ...$this->at])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'emulator'));
});

it('flags instead of blocking a rooted device when integrity enforcement is flag', function (): void {
    ($this->setPolicy)(['integrity_enforcement' => 'flag']);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', 'is_rooted' => true, ...$this->at])
        ->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->risk_flags)->toContain('rooted');
    expect($att->integrity_verdict)->toBe('flagged');
});

it('records a mismatched face as a flag when face enforcement is flag', function (): void {
    ($this->setPolicy)(['face_enforcement' => 'flag']);
    Http::fake([
        '*/v1/faces/enroll' => Http::response(hardeningEnrollmentResponse()),
        '*/v1/faces/verify' => Http::response([
            'matched' => false,
            'score' => 0.2,
            'threshold' => 0.6,
            'quality_passed' => true,
            'quality_reasons' => [],
        ]),
    ]);

    ($this->auth)()->post('/api/v1/me/face/enroll', ['images' => hardeningFaceImages()])->assertOk();

    ($this->auth)()->post('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'selfie' => UploadedFile::fake()->image('mismatch.jpg', 480, 640),
        ...$this->at,
    ])
        ->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->risk_flags)->toContain('face_mismatch');
});

it('exposes face_mode and device binding in today status', function (): void {
    ($this->setPolicy)(['face_mode' => 'detection', 'device_binding_enabled' => false]);

    ($this->auth)()->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        ->assertJsonPath('requirements.face_mode', 'detection')
        ->assertJsonPath('requirements.device_binding_enabled', false);
});

it('tells the app whether a failed face blocks the punch or only flags it', function (): void {
    ($this->setPolicy)(['face_enforcement' => 'flag']);

    ($this->auth)()->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        ->assertJsonPath('requirements.face_enforcement', 'flag');

    ($this->setPolicy)(['face_enforcement' => 'block']);

    ($this->auth)()->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        ->assertJsonPath('requirements.face_enforcement', 'block');
});

it('skips the face check entirely when face_mode is off', function (): void {
    Http::fake(['*/v1/faces/enroll' => Http::response(hardeningEnrollmentResponse())]);
    ($this->auth)()->post('/api/v1/me/face/enroll', ['images' => hardeningFaceImages()])->assertOk();

    ($this->setPolicy)(['face_mode' => 'off', 'face_enforcement' => 'block']);

    // Enrolled + strict block would normally demand a matching face, but with
    // face off, clocking in with no face at all succeeds.
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', ...$this->at])
        ->assertOk();
});

it('accepts any live face without matching when face_mode is detection', function (): void {
    ($this->setPolicy)(['face_mode' => 'detection', 'face_enforcement' => 'block']);
    Http::fake(['*/v1/faces/detect' => Http::response([
        'faces' => [['bbox' => [1, 1, 100, 100], 'landmarks' => [], 'score' => 0.9]],
        'image_size' => [480, 640],
        'face_count' => 1,
    ])]);

    ($this->auth)()->post('/api/v1/me/attendance/clock', [
        'type' => 'in',
        'selfie' => UploadedFile::fake()->image('detected.jpg', 480, 640),
        ...$this->at,
    ])
        ->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->risk_flags ?? [])->not->toContain('face_mismatch');
});
