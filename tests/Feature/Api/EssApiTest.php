<?php

use App\Models\LeaveType;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    Storage::fake('public');

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ])->json('access_token');

    // jwt-auth caches the resolved user on the guard singleton across requests
    // in a test; flush it before each call so the bearer token is the sole auth.
    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };
});

it('returns the employee profile', function (): void {
    ($this->auth)()->getJson('/api/v1/me/profile')->assertOk()
        ->assertJsonStructure(['data' => ['employee_no', 'full_name', 'employment' => ['department', 'position']]]);
});

it('updates the profile', function (): void {
    ($this->auth)()->putJson('/api/v1/me/profile', ['phone' => '0812-0000-0000', 'address' => 'Jl. Baru 1'])
        ->assertOk()->assertJsonPath('data.phone', '0812-0000-0000');
});

it('clocks in via the unified endpoint with GPS + selfie', function (): void {
    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2, 'longitude' => 106.8,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertOk()->assertJsonPath('data.next_action', 'out');
});

it('returns today status and history', function (): void {
    ($this->auth)()->getJson('/api/v1/me/attendance/today')->assertOk()
        ->assertJsonStructure(['data' => ['date', 'next_action', 'summary' => ['status', 'work_minutes']]]);
    ($this->auth)()->getJson('/api/v1/me/attendance')->assertOk()->assertJsonStructure(['data', 'meta']);
});

it('lists leave balances, types and submits a request', function (): void {
    ($this->auth)()->getJson('/api/v1/me/leave/balances')->assertOk()
        ->assertJsonStructure(['data' => [['leave_type', 'year', 'entitled', 'used', 'available']]]);
    ($this->auth)()->getJson('/api/v1/me/leave-types')->assertOk()->assertJsonStructure(['data']);

    $type = LeaveType::where('code', 'TAHUNAN')->firstOrFail();
    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Keluarga',
    ])->assertCreated();
});

it('rejects a leave request over balance', function (): void {
    $type = LeaveType::where('code', 'PENTING')->firstOrFail();
    ($this->auth)()->postJson('/api/v1/me/leave-requests', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
    ])->assertStatus(422);
});

it('submits overtime, permission and wfh, and lists announcements', function (): void {
    ($this->auth)()->postJson('/api/v1/me/overtime', ['date' => now()->toDateString(), 'hours' => 2, 'reason' => 'Deadline'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/permissions', ['date' => now()->toDateString(), 'type' => 'keluar', 'start_time' => '10:00', 'end_time' => '11:00'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/wfh', ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()])->assertCreated();
    ($this->auth)()->getJson('/api/v1/me/announcements')->assertOk()->assertJsonStructure(['data']);
});

it('submits a reimbursement', function (): void {
    ($this->auth)()->postJson('/api/v1/me/reimbursements', [
        'category' => 'transport', 'amount' => 85000,
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertCreated();
});

it('lists payslips and notifications with the expected envelope', function (): void {
    ($this->auth)()->getJson('/api/v1/me/payslips')->assertOk()->assertJsonStructure(['data']);
    ($this->auth)()->getJson('/api/v1/me/notifications')->assertOk()->assertJsonStructure(['data', 'meta' => ['unread']]);
});

it('forbids a non-employee user (admin) from ESS endpoints', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'admin@avanahr.co.id', 'password' => 'password'])->json('access_token');
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/profile')
        ->assertForbidden();
});
