<?php

use App\Models\Company;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserLoginDevice;
use App\Services\LoginSecurity;
use Illuminate\Support\Facades\Queue;

function securityTestUser(array $overrides = []): User
{
    static $sequence = 0;
    $sequence++;

    $tenant = Tenant::firstOrCreate(
        ['slug' => 'tenant-keamanan'],
        ['name' => 'PT Uji Keamanan', 'status' => 'active'],
    );

    Company::firstOrCreate(['tenant_id' => $tenant->id], ['name' => 'PT Uji Keamanan']);

    return User::create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Pengguna Keamanan '.$sequence,
        'email' => 'keamanan'.$sequence.'@example.test',
        'password' => 'rahasia-panjang-sekali',
        'status' => 'active',
        'email_verified_at' => now(),
    ], $overrides));
}

test('a sign-in is remembered as a known device', function () {
    $user = securityTestUser();

    $device = LoginSecurity::recordLogin(
        $user,
        request()->merge([])->setUserResolver(fn () => $user),
    );

    expect($device)->not->toBeNull()
        ->and(UserLoginDevice::where('user_id', $user->id)->count())->toBe(1)
        ->and($device->login_count)->toBe(1);
});

test('signing in again from the same browser updates the row instead of adding one', function () {
    $user = securityTestUser();

    LoginSecurity::recordLogin($user);
    LoginSecurity::recordLogin($user);

    $devices = UserLoginDevice::where('user_id', $user->id)->get();

    expect($devices)->toHaveCount(1)
        ->and($devices->first()->login_count)->toBe(2);
});

test('the first device an account ever uses raises no alert', function () {
    Queue::fake();
    $user = securityTestUser();

    LoginSecurity::recordLogin($user);

    expect(Notification::where('user_id', $user->id)->where('type', 'security')->count())->toBe(0);
});

test('a second, unfamiliar device warns the account owner', function () {
    Queue::fake();
    $user = securityTestUser();

    LoginSecurity::recordLogin($user, deviceKey: 'laptop-kantor');
    LoginSecurity::recordLogin($user, deviceKey: 'perangkat-asing');

    $alert = Notification::where('user_id', $user->id)->where('type', 'security')->first();

    expect($alert)->not->toBeNull()
        ->and($alert->title)->toBe('Login dari perangkat baru')
        ->and(UserActivityLog::where('user_id', $user->id)->where('event', 'login_new_device')->exists())->toBeTrue();
});

test('web and app sign-ins from the same handset stay separate devices', function () {
    Queue::fake();
    $user = securityTestUser();

    LoginSecurity::recordLogin($user, channel: 'web', deviceKey: 'handset-1');
    LoginSecurity::recordLogin($user, channel: 'mobile', deviceKey: 'handset-1');

    expect(UserLoginDevice::where('user_id', $user->id)->count())->toBe(2);
});

test('a lockout is logged and the account owner is told once per cooldown', function () {
    Queue::fake();
    $user = securityTestUser();

    LoginSecurity::recordLockout($user->email);
    LoginSecurity::recordLockout($user->email);

    expect(UserActivityLog::where('event', 'login_lockout')->count())->toBe(2)
        ->and(Notification::where('user_id', $user->id)->where('type', 'security')->count())->toBe(1);
});

test('a lockout on an address with no account notifies nobody', function () {
    Queue::fake();

    LoginSecurity::recordLockout('bukan-siapa-siapa@example.test');

    expect(UserActivityLog::where('event', 'login_lockout')->count())->toBe(1)
        ->and(Notification::where('type', 'security')->count())->toBe(0);
});

test('too many failed logins lock the address out and record it', function () {
    $user = securityTestUser();

    foreach (range(1, 6) as $attempt) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'salah-terus',
        ]);
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'salah-terus',
    ])->assertSessionHasErrors('email');

    expect(UserActivityLog::where('event', 'login_lockout')->exists())->toBeTrue();
});

test('the user agent is turned into something a person can recognise', function () {
    $chrome = LoginSecurity::describe(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'
    );

    expect($chrome['label'])->toBe('Chrome · Windows');

    expect(LoginSecurity::describe('')['label'])->toBe('Perangkat tidak dikenal');
});

test('a platform account with no tenant still gets the email, without an in-app row', function () {
    Queue::fake();

    $user = securityTestUser(['tenant_id' => null]);

    LoginSecurity::recordLogin($user, deviceKey: 'laptop-lama');
    LoginSecurity::recordLogin($user, deviceKey: 'perangkat-asing');

    expect(Notification::where('user_id', $user->id)->count())->toBe(0)
        ->and(UserActivityLog::where('user_id', $user->id)->where('event', 'login_new_device')->exists())->toBeTrue();
});

test('a first-ever mobile login does not double-book itself as web plus mobile', function (): void {
    Queue::fake();
    $user = securityTestUser();

    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'rahasia-panjang-sekali',
    ])->json('access_token');

    expect($token)->not->toBeNull()
        ->and(UserLoginDevice::where('user_id', $user->id)->count())->toBe(1)
        ->and(Notification::where('user_id', $user->id)->where('type', 'security')->count())->toBe(0);
});
