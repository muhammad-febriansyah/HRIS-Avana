<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
});

it('lists active colleagues with pagination meta', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/me/directory')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'employee_number', 'position', 'department', 'initials', 'avatar_color', 'is_me']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ])
        ->assertJsonPath('data.0.is_me', fn ($v): bool => is_bool($v));
});

it('flags the caller with is_me', function (): void {
    $res = ($this->auth)()->getJson('/api/v1/me/directory?search='.urlencode($this->me->full_name))->assertOk();

    $mine = collect($res->json('data'))->firstWhere('id', $this->me->id);

    expect($mine['is_me'])->toBeTrue();
});

it('filters the directory by search term', function (): void {
    $target = Employee::forTenant($this->me->tenant_id)
        ->where('id', '!=', $this->me->id)
        ->where('status', 'active')
        ->firstOrFail();

    $res = ($this->auth)()
        ->getJson('/api/v1/me/directory?search='.urlencode($target->employee_number))
        ->assertOk();

    expect(collect($res->json('data'))->pluck('id'))->toContain($target->id);
    expect($res->json('meta.total'))->toBeLessThan(Employee::forTenant($this->me->tenant_id)->where('status', 'active')->count());
});

it('shows a colleague contact card with their manager', function (): void {
    $target = Employee::forTenant($this->me->tenant_id)
        ->where('id', '!=', $this->me->id)
        ->where('status', 'active')
        ->firstOrFail();
    $target->update(['manager_id' => $this->me->id, 'phone' => '0811223344', 'email' => 'kolega@nusantara.co.id']);

    ($this->auth)()
        ->getJson('/api/v1/me/directory/'.$target->id)
        ->assertOk()
        ->assertJsonPath('data.email', 'kolega@nusantara.co.id')
        ->assertJsonPath('data.phone', '0811223344')
        ->assertJsonPath('data.manager.name', $this->me->full_name)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'branch', 'manager' => ['id', 'name', 'position']]]);
});

it('does not expose employees from another tenant', function (): void {
    $foreign = Employee::where('tenant_id', '!=', $this->me->tenant_id)->first();

    if ($foreign === null) {
        $this->markTestSkipped('No cross-tenant employee in the demo seed.');
    }

    ($this->auth)()->getJson('/api/v1/me/directory/'.$foreign->id)->assertNotFound();
});
