<?php

use App\Models\AttendancePolicy;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('keeps a tenant on the liveness challenge when the policy is saved', function (): void {
    AttendancePolicy::updateOrCreate(
        ['tenant_id' => $this->tenant->id],
        ['require_liveness_challenge' => true],
    );

    // The screen no longer draws the switch, but it still carries the value —
    // a save from that page must not quietly turn it off.
    actingAs($this->admin)
        ->put(route('avana.absensi.kebijakan.update'), [
            'attendance_scope' => AttendancePolicy::SCOPE_ASSIGNED,
            'device_binding_enabled' => true,
            'require_face_enrollment' => false,
            'face_mode' => AttendancePolicy::FACE_MODE_RECOGNITION,
            'require_liveness_challenge' => true,
            'face_enforcement' => 'block',
            'integrity_enforcement' => 'block',
            'block_mock_location' => true,
            'block_rooted' => true,
            'block_emulator' => true,
        ])
        ->assertRedirect();

    expect((bool) AttendancePolicy::resolve($this->tenant->id)->require_liveness_challenge)->toBeTrue();
});

it('still tells the app the challenge is required', function (): void {
    AttendancePolicy::updateOrCreate(
        ['tenant_id' => $this->tenant->id],
        ['require_liveness_challenge' => true],
    );

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/attendance/today')
        ->assertOk()
        ->assertJsonPath('requirements.require_liveness_challenge', true);
});
