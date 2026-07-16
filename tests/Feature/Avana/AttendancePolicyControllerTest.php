<?php

use App\Models\AttendancePolicy;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the attendance policy screen for an HR admin', function (): void {
    actingAs($this->admin)->get('/avana/absensi/kebijakan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi-kebijakan/index')
            ->has('policy')
            ->where('policy.face_enforcement', 'block'));
});

it('persists policy changes', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'attendance_scope' => 'assigned',
        'require_face_enrollment' => true,
        'require_liveness_challenge' => true,
        'face_enforcement' => 'flag',
        'integrity_enforcement' => 'block',
        'block_mock_location' => true,
        'block_rooted' => true,
        'block_emulator' => false,
    ])->assertRedirect();

    $policy = AttendancePolicy::where('tenant_id', $this->admin->tenant_id)->firstOrFail();
    expect($policy->require_face_enrollment)->toBeTrue();
    expect($policy->face_enforcement)->toBe('flag');
    expect($policy->block_emulator)->toBeFalse();
});

it('persists the attendance scope', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'attendance_scope' => 'anywhere',
        'face_enforcement' => 'block',
        'integrity_enforcement' => 'block',
    ])->assertRedirect();

    expect(AttendancePolicy::where('tenant_id', $this->admin->tenant_id)->firstOrFail()->attendance_scope)
        ->toBe('anywhere');
});

it('rejects an unknown attendance scope', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'attendance_scope' => 'mars',
        'face_enforcement' => 'block',
        'integrity_enforcement' => 'block',
    ])->assertSessionHasErrors('attendance_scope');
});

it('rejects an invalid enforcement value', function (): void {
    actingAs($this->admin)->put('/avana/absensi/kebijakan', [
        'face_enforcement' => 'ignore',
        'integrity_enforcement' => 'block',
    ])->assertSessionHasErrors('face_enforcement');
});

it('forbids a plain employee from managing the policy', function (): void {
    $employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    actingAs($employee)->get('/avana/absensi/kebijakan')->assertForbidden();
});
