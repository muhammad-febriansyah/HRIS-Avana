<?php

use App\Models\Attendance;
use App\Models\AttendanceChallenge;
use App\Models\AttendancePolicy;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;
    $this->tenantId = $this->employee->tenant_id;

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

    // Enroll a known vector, then clock with an orthogonal one (low similarity).
    $enrolled = array_fill(0, 128, 0.0);
    $enrolled[0] = 1.0;
    ($this->auth)()->postJson('/api/v1/me/face/enroll', ['embedding' => $enrolled])->assertOk();

    $incoming = array_fill(0, 128, 0.0);
    $incoming[1] = 1.0;

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', ['type' => 'in', 'face_embedding' => $incoming, ...$this->at])
        ->assertOk();

    $att = Attendance::whereNotNull('clock_in_at')->latest('id')->firstOrFail();
    expect($att->risk_flags)->toContain('face_mismatch');
});
