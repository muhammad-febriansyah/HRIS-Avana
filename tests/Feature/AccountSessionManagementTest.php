<?php

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserLoginDevice;
use App\Services\LoginSecurity;
use App\Services\SessionRegistry;
use Illuminate\Support\Facades\DB;

function sessionTestUser(): User
{
    static $sequence = 0;
    $sequence++;

    $tenant = Tenant::firstOrCreate(
        ['slug' => 'tenant-sesi'],
        ['name' => 'PT Uji Sesi', 'status' => 'active'],
    );

    return User::create([
        'tenant_id' => $tenant->id,
        'name' => 'Pengguna Sesi '.$sequence,
        'email' => 'sesi'.$sequence.'@example.test',
        'password' => 'rahasia-panjang-sekali',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);
}

/** Plant a session row as if the user were signed in from another browser. */
function plantSession(User $user, string $id, string $agent = 'Mozilla/5.0 (Macintosh) Safari/605'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.9',
        'user_agent' => $agent,
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->timestamp,
    ]);
}

test('the security page lists sessions, devices, and login history', function () {
    config(['session.driver' => 'database']);

    $user = sessionTestUser();
    LoginSecurity::recordLogin($user, deviceKey: 'laptop-kantor');
    plantSession($user, 'sesi-lain-1');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/security')
            ->where('sessionsAvailable', true)
            ->has('sessions', 1)
            ->has('devices', 1)
            ->has('loginHistory')
        );
});

test('login history supports search and pagination', function () {
    $user = sessionTestUser();

    foreach (range(1, 11) as $index) {
        UserActivityLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event' => 'login',
            'description' => $index === 11 ? 'Login dari kantor pusat' : 'Login biasa',
            'ip_address' => '10.0.0.'.$index,
            'user_agent' => 'Mozilla/5.0 (Macintosh) Safari/605',
            'created_at' => now()->subMinutes($index),
        ]);
    }

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit', ['search' => 'kantor']))
        ->assertInertia(fn ($page) => $page
            ->component('settings/security')
            ->where('loginHistory.search', 'kantor')
            ->where('loginHistory.meta.total', 1)
            ->where('loginHistory.data.0.description', 'Login dari kantor pusat')
        );

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('loginHistory.meta.current_page', 2)
            ->where('loginHistory.meta.total', 11)
            ->has('loginHistory.data', 1)
        );
});

test('a user can end another session but not the one they are using', function () {
    config(['session.driver' => 'database']);

    $user = sessionTestUser();
    plantSession($user, 'sesi-lain-2');

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('security.sessions.destroy', ['session' => 'sesi-lain-2']))
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'sesi-lain-2')->exists())->toBeFalse();
});

test('one account cannot end another account session', function () {
    config(['session.driver' => 'database']);

    $owner = sessionTestUser();
    $intruder = sessionTestUser();
    plantSession($owner, 'sesi-milik-orang-lain');

    $this->actingAs($intruder)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('security.sessions.destroy', ['session' => 'sesi-milik-orang-lain']));

    expect(DB::table('sessions')->where('id', 'sesi-milik-orang-lain')->exists())->toBeTrue();
});

test('ending other sessions leaves the current one alone', function () {
    config(['session.driver' => 'database']);

    $user = sessionTestUser();
    plantSession($user, 'sesi-a');
    plantSession($user, 'sesi-b');

    expect(SessionRegistry::revokeOthers($user, 'sesi-a'))->toBe(1)
        ->and(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe(['sesi-a']);
});

test('revoking a device marks it revoked and kills its sessions', function () {
    config(['session.driver' => 'database']);

    $user = sessionTestUser();
    $agent = 'Mozilla/5.0 (Macintosh) Safari/605';

    $device = UserLoginDevice::create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'fingerprint' => LoginSecurity::fingerprint($agent),
        'label' => 'Safari · macOS',
        'channel' => 'web',
        'login_count' => 3,
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now(),
    ]);

    plantSession($user, 'sesi-perangkat', $agent);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('security.devices.destroy', ['device' => $device->id]))
        ->assertRedirect();

    expect($device->fresh()->revoked_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'sesi-perangkat')->exists())->toBeFalse();
});

test('a device belonging to someone else cannot be revoked', function () {
    $owner = sessionTestUser();
    $intruder = sessionTestUser();

    $device = UserLoginDevice::create([
        'tenant_id' => $owner->tenant_id,
        'user_id' => $owner->id,
        'fingerprint' => str_repeat('a', 64),
        'label' => 'Chrome · Windows',
        'channel' => 'web',
    ]);

    $this->actingAs($intruder)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('security.devices.destroy', ['device' => $device->id]))
        ->assertForbidden();

    expect($device->fresh()->revoked_at)->toBeNull();
});

test('session management needs a recently confirmed password', function () {
    $user = sessionTestUser();

    $this->actingAs($user)
        ->delete(route('security.sessions.destroy-others'))
        ->assertRedirect(route('password.confirm'));
});

test('the session list reports itself unavailable on a non-database driver', function () {
    config(['session.driver' => 'file']);

    expect(SessionRegistry::available())->toBeFalse()
        ->and(SessionRegistry::forUser(sessionTestUser())->all())->toBe([]);
});
