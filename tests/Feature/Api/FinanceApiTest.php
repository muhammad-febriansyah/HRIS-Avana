<?php

use App\Models\Notification;
use App\Models\Reimbursement;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->finance = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->claimant = $this->employeeUser->employee;

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };

    $this->claim = Reimbursement::create([
        'tenant_id' => $this->claimant->tenant_id,
        'employee_id' => $this->claimant->id,
        'number' => 'RMB-TEST-0001',
        'category' => 'transportasi',
        'title' => 'Taksi bandara',
        'amount' => 250000,
        'expense_date' => now()->toDateString(),
        'status' => 'approved',
        'approved_at' => now(),
    ]);
});

it('lists the approved-unpaid reimbursement queue for finance', function (): void {
    $token = ($this->tokenFor)('superadmin@avanahr.id');

    ($this->auth)($token)
        ->getJson('/api/v1/finance/reimbursements')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'title', 'amount', 'status', 'employee' => ['name', 'employee_number']]]])
        ->assertJsonFragment(['id' => $this->claim->id, 'status' => 'approved']);
});

it('pays an approved claim and notifies the employee', function (): void {
    $token = ($this->tokenFor)('superadmin@avanahr.id');

    ($this->auth)($token)
        ->postJson('/api/v1/finance/reimbursements/'.$this->claim->id.'/pay')
        ->assertOk();

    $fresh = $this->claim->fresh();
    expect($fresh->status)->toBe('paid');
    expect($fresh->paid_at)->not->toBeNull();
    expect($fresh->paid_by)->toBe($this->finance->id);

    $notification = Notification::where('user_id', $this->claimant->user_id)
        ->where('type', 'reimburse')
        ->firstOrFail();

    expect($notification->data)->toMatchArray([
        'link' => ['type' => 'reimburse', 'id' => $this->claim->id],
        'status' => 'paid',
    ]);
});

it('refuses to pay a claim that is not yet approved', function (): void {
    $this->claim->update(['status' => 'pending', 'approved_at' => null]);
    $token = ($this->tokenFor)('superadmin@avanahr.id');

    ($this->auth)($token)
        ->postJson('/api/v1/finance/reimbursements/'.$this->claim->id.'/pay')
        ->assertStatus(422);

    expect($this->claim->fresh()->status)->toBe('pending');
});

it('forbids a non-finance employee from the finance endpoints', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)->getJson('/api/v1/finance/reimbursements')->assertStatus(403);
    ($this->auth)($token)->postJson('/api/v1/finance/reimbursements/'.$this->claim->id.'/pay')->assertStatus(403);
});
