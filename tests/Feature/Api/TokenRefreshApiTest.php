<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $this->login = function (): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', [
            'email' => 'bagus.p@nusantara.co.id',
            'password' => 'password',
        ])->json('access_token');
    };

    $this->withToken = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('exchanges a live token for a new working one', function (): void {
    $token = ($this->login)();

    $fresh = ($this->withToken)($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->json('access_token');

    expect($fresh)->not->toBe($token);

    ($this->withToken)($fresh)->getJson('/api/v1/auth/me')->assertOk();
});

/**
 * The reason the route sits outside `auth:api`: a client only discovers its
 * token has lapsed when something 401s, which is necessarily after expiry.
 */
it('exchanges a token that has already expired', function (): void {
    $token = ($this->login)();

    $this->travel(config('jwt.ttl') + 5)->minutes();

    ($this->withToken)($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

    $fresh = ($this->withToken)($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertOk()
        ->json('access_token');

    ($this->withToken)($fresh)->getJson('/api/v1/auth/me')->assertOk();
});

it('refuses a token older than the refresh window', function (): void {
    $token = ($this->login)();

    $this->travel(config('jwt.refresh_ttl') + 60)->minutes();

    ($this->withToken)($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Sesi telah berakhir. Silakan masuk kembali.');
});

it('refuses to refresh a session that was signed out everywhere', function (): void {
    $token = ($this->login)();

    // What a password change or "keluar dari semua perangkat" does.
    $this->user->increment('token_version');

    ($this->withToken)($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized();
});

it('refuses to refresh for a deactivated account', function (): void {
    $token = ($this->login)();

    $this->user->update(['status' => 'inactive']);

    ($this->withToken)($token)
        ->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized();
});

it('refuses a request with no bearer at all', function (): void {
    $this->postJson('/api/v1/auth/refresh')->assertUnauthorized();
});

it('retires the old token once it has been exchanged', function (): void {
    $token = ($this->login)();

    ($this->withToken)($token)->postJson('/api/v1/auth/refresh')->assertOk();

    ($this->withToken)($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});
