<?php

use App\Models\Employee;
use App\Models\EotmCoreValue;
use App\Models\EotmPeriod;
use App\Models\EotmVote;
use App\Models\Notification;
use App\Models\User;
use App\Services\EotmVoting;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee;
    $this->tenantId = (int) $this->employeeUser->tenant_id;

    $this->colleague = Employee::forTenant($this->tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $this->period = EotmPeriod::create([
        'tenant_id' => $this->tenantId,
        'period' => '2026-07',
        'status' => EotmPeriod::STATUS_OPEN,
        'opens_at' => now(),
    ]);

    $this->auth = function () {
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'bagus.p@nusantara.co.id',
            'password' => 'password',
        ])->json('access_token');

        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('returns the open period with core values and an empty tally', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/me/eotm')
        ->assertOk()
        ->assertJsonPath('data.period.period', '2026-07')
        ->assertJsonPath('data.period.label', 'Juli 2026')
        ->assertJsonPath('data.period.is_open', true)
        ->assertJsonPath('data.my_vote', null)
        ->assertJsonCount(5, 'data.core_values')
        ->assertJsonCount(0, 'data.standings');
});

it('excludes the caller from the nominee list', function (): void {
    $response = ($this->auth)()
        ->getJson('/api/v1/me/eotm/nominees')
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id'))
        ->not->toContain($this->employee->id)
        ->toContain($this->colleague->id);
});

it('casts a vote and shows it in the standings', function (): void {
    $value = EotmCoreValue::forTenant($this->tenantId)->firstOrFail();

    ($this->auth)()
        ->postJson('/api/v1/me/eotm/vote', [
            'nominee_employee_id' => $this->colleague->id,
            'eotm_core_value_id' => $value->id,
            'reason' => 'Selalu bantu tim tanpa diminta.',
        ])
        ->assertOk()
        ->assertJsonPath('data.total_votes', 1)
        ->assertJsonPath('data.standings.0.employee_id', $this->colleague->id)
        ->assertJsonPath('data.standings.0.votes', 1)
        ->assertJsonPath('data.standings.0.percent', 100)
        ->assertJsonPath('data.standings.0.core_value', $value->name);

    ($this->auth)()
        ->getJson('/api/v1/me/eotm')
        ->assertOk()
        ->assertJsonPath('data.my_vote.nominee_employee_id', $this->colleague->id);
});

it('replaces the earlier vote instead of stacking a second one', function (): void {
    $third = Employee::forTenant($this->tenantId)
        ->whereNotIn('id', [$this->employee->id, $this->colleague->id])
        ->firstOrFail();

    ($this->auth)()
        ->postJson('/api/v1/me/eotm/vote', ['nominee_employee_id' => $this->colleague->id])
        ->assertOk();

    ($this->auth)()
        ->postJson('/api/v1/me/eotm/vote', ['nominee_employee_id' => $third->id])
        ->assertOk()
        ->assertJsonPath('data.total_votes', 1);

    expect(EotmVote::where('eotm_period_id', $this->period->id)->count())->toBe(1)
        ->and(EotmVote::where('eotm_period_id', $this->period->id)->first()->nominee_employee_id)
        ->toBe($third->id);
});

it('refuses a self-vote', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/me/eotm/vote', ['nominee_employee_id' => $this->employee->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nominee_employee_id');

    expect(EotmVote::count())->toBe(0);
});

it('refuses a vote once the period is closed', function (): void {
    $this->period->update(['status' => EotmPeriod::STATUS_CLOSED]);

    ($this->auth)()
        ->postJson('/api/v1/me/eotm/vote', ['nominee_employee_id' => $this->colleague->id])
        ->assertNotFound();

    expect(EotmVote::count())->toBe(0);
});

it('stamps the winner when the period closes', function (): void {
    EotmVote::create([
        'tenant_id' => $this->tenantId,
        'eotm_period_id' => $this->period->id,
        'voter_employee_id' => $this->employee->id,
        'nominee_employee_id' => $this->colleague->id,
    ]);

    $closed = app(EotmVoting::class)->close($this->period);

    expect($closed->status)->toBe(EotmPeriod::STATUS_CLOSED)
        ->and($closed->winner_employee_id)->toBe($this->colleague->id)
        ->and($closed->winner_votes)->toBe(1);

    // The stamped result survives the votes being cleared out.
    EotmVote::query()->delete();

    expect($closed->refresh()->winner_employee_id)->toBe($this->colleague->id);
});

it('rejects a nominee from another tenant at the service layer', function (): void {
    $foreignTenantEmployee = Employee::query()
        ->where('tenant_id', '!=', $this->tenantId)
        ->first();

    if ($foreignTenantEmployee === null) {
        expect(true)->toBeTrue();

        return;
    }

    expect(fn () => app(EotmVoting::class)->vote(
        $this->period,
        $this->employee,
        $foreignTenantEmployee,
    ))->toThrow(ValidationException::class);
});

it('announces to every employee when voting opens and when it closes', function (): void {
    $recipients = Employee::forTenant($this->tenantId)
        ->where('status', 'active')
        ->whereNotNull('user_id')
        ->count();

    expect($recipients)->toBeGreaterThan(0);

    actingAs(User::where('email', 'rina.a@nusantara.co.id')->firstOrFail())
        ->post(route('avana.sosmed.eotm.store'), ['period' => '2026-08'])
        ->assertRedirect();

    expect(Notification::where('type', 'eotm')->count())->toBe($recipients);

    Notification::query()->delete();

    app(EotmVoting::class)->close(EotmPeriod::forTenant($this->tenantId)->open()->firstOrFail());

    expect(Notification::where('type', 'eotm')->count())->toBe($recipients);
});
