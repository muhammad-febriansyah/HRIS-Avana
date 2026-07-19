<?php

use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee;

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

function apiAdvance(Employee $employee, string $status = 'pending'): CashAdvance
{
    return CashAdvance::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'amount' => 2_000_000,
        'purpose' => 'Uang muka dinas Bandung',
        'request_date' => '2026-07-18',
        'needed_date' => '2026-07-25',
        'status' => $status,
    ]);
}

it('lists only the caller own advances', function (): void {
    $mine = apiAdvance($this->employee);

    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();
    $theirs = apiAdvance($colleague);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $ids = collect(
        ($this->auth)($token)
            ->getJson('/api/v1/me/cash-advances')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'amount', 'purpose', 'status', 'status_label', 'request_date']]])
            ->json('data')
    )->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('returns the disbursement trail on the detail', function (): void {
    $advance = apiAdvance($this->employee, 'disbursed');
    $advance->update([
        'approved_at' => now()->subDay(),
        'disbursed_at' => now(),
        'disbursement_method' => 'transfer',
        'disbursement_reference' => 'TRF-9001',
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $body = ($this->auth)($token)
        ->getJson("/api/v1/me/cash-advances/{$advance->id}")
        ->assertOk()
        ->json('data');

    expect($body['status_label'])->toBe('Dicairkan')
        ->and($body['disbursement_reference'])->toBe('TRF-9001')
        ->and(collect($body['timeline'])->pluck('key')->all())
        ->toBe(['requested', 'approved', 'disbursed'])
        ->and(collect($body['timeline'])->every(fn (array $s): bool => $s['done']))->toBeTrue();
});

it('hides an advance belonging to another employee', function (): void {
    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();
    $theirs = apiAdvance($colleague);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson("/api/v1/me/cash-advances/{$theirs->id}")
        ->assertNotFound();
});

it('files a new advance as pending', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $response = ($this->auth)($token)
        ->postJson('/api/v1/me/cash-advances', [
            'amount' => 1_500_000,
            'purpose' => 'Uang muka kunjungan klien',
            'needed_date' => now()->addWeek()->toDateString(),
            'reason' => 'Tiket dan penginapan dibayar di muka',
        ])
        ->assertCreated();

    $advance = CashAdvance::findOrFail($response->json('data.id'));

    expect($advance->employee_id)->toBe($this->employee->id)
        ->and($advance->status)->toBe('pending')
        ->and((float) $advance->amount)->toBe(1_500_000.0);
});

it('refuses an advance needed in the past', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/cash-advances', [
            'amount' => 500_000,
            'purpose' => 'Terlambat',
            'needed_date' => now()->subWeek()->toDateString(),
        ])
        ->assertJsonValidationErrors('needed_date');
});

it('refuses an unauthenticated caller', function (): void {
    $this->getJson('/api/v1/me/cash-advances')->assertUnauthorized();
});
