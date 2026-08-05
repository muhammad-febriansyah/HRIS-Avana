<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\OvertimeRules;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    OvertimeRules::forget();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenant = Tenant::findOrFail(
        User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->tenant_id,
    );

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

it('tells the phone the rounding rule it will be judged by', function (): void {
    OvertimeRules::forget();
    OvertimeRules::policyFor($this->tenant->id)->update(['rounding_minutes' => 30]);
    OvertimeRules::forget();

    ($this->auth)()->getJson('/api/v1/me/overtime')
        ->assertOk()
        ->assertJsonPath('policy.rounding_minutes', 30)
        // Under one block a filing is worth nothing, so the app can say so
        // before the server has to.
        ->assertJsonPath('policy.min_hours', 0.5)
        ->assertJsonPath('policy.max_hours', 12);
});

it('reports no rounding when the tenant sets none', function (): void {
    OvertimeRules::forget();
    OvertimeRules::policyFor($this->tenant->id)->update(['rounding_minutes' => 0]);
    OvertimeRules::forget();

    ($this->auth)()->getJson('/api/v1/me/overtime')
        ->assertOk()
        ->assertJsonPath('policy.rounding_minutes', 0)
        ->assertJsonPath('policy.min_hours', 0.5);
});

it('stores the rounded hours a phone filing is worth', function (): void {
    OvertimeRules::forget();
    OvertimeRules::policyFor($this->tenant->id)->update(['rounding_minutes' => 30]);
    OvertimeRules::forget();

    // 18:00–18:45 = 45 minutes, paid as half an hour.
    ($this->auth)()->postJson('/api/v1/me/overtime', [
        'date' => now()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '18:45',
    ])->assertCreated();

    expect((float) ($this->auth)()->getJson('/api/v1/me/overtime')->json('data.0.hours'))->toBe(0.5);
});

it('refuses a phone filing shorter than one block, in words', function (): void {
    OvertimeRules::forget();
    OvertimeRules::policyFor($this->tenant->id)->update(['rounding_minutes' => 30]);
    OvertimeRules::forget();

    ($this->auth)()->postJson('/api/v1/me/overtime', [
        'date' => now()->toDateString(),
        'start_time' => '18:00',
        'end_time' => '18:20',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Lembur kurang dari 30 menit tidak dihitung.');
});
