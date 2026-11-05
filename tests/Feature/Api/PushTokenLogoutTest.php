<?php

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
});

function pushLogin(string $email, string $deviceId = 'shared-phone'): string
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/login', [
        'device_id' => $deviceId,
        'email' => $email,
        'password' => 'password',
    ])->json('access_token');
}

it('drops the phone push token when the account signs out', function (): void {
    $token = pushLogin('bagus.p@nusantara.co.id');
    $user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    app('auth')->forgetGuards();
    test()->withToken($token)
        ->postJson('/api/v1/me/security/fcm-token', ['device_id' => 'shared-phone', 'fcm_token' => 'fcm-abc'])
        ->assertOk();

    expect(UserDevice::where('user_id', $user->id)->value('fcm_token'))->toBe('fcm-abc');

    app('auth')->forgetGuards();
    test()->withToken($token)
        ->postJson('/api/v1/auth/logout', ['device_id' => 'shared-phone'])
        ->assertOk();

    expect(UserDevice::where('user_id', $user->id)->value('fcm_token'))->toBeNull();
});

it('moves a push token to whoever registers it last', function (): void {
    $first = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $second = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    $firstToken = pushLogin($first->email);
    app('auth')->forgetGuards();
    test()->withToken($firstToken)
        ->postJson('/api/v1/me/security/fcm-token', ['device_id' => 'shared-phone', 'fcm_token' => 'fcm-shared'])
        ->assertOk();

    $secondToken = pushLogin($second->email);
    app('auth')->forgetGuards();
    test()->withToken($secondToken)
        ->postJson('/api/v1/me/security/fcm-token', ['device_id' => 'shared-phone', 'fcm_token' => 'fcm-shared'])
        ->assertOk();

    expect(UserDevice::where('user_id', $first->id)->value('fcm_token'))->toBeNull()
        ->and(UserDevice::where('user_id', $second->id)->value('fcm_token'))->toBe('fcm-shared');
});
