<?php

use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    Storage::fake('public');

    $this->token = $this->postJson('/api/v1/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ])->json('token');

    // jwt-auth caches the resolved user on the guard singleton across requests
    // in a test; flush it before each call so the bearer token is the sole auth.
    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };
});

it('clocks in with GPS and a selfie', function (): void {
    ($this->auth)()->postJson('/api/v1/attendance/clock-in', [
        'lat' => -6.2, 'lng' => 106.8,
        'selfie' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertOk()->assertJsonPath('data.status', 'present');
});

it('returns attendance history and today status', function (): void {
    ($this->auth)()->getJson('/api/v1/attendance')->assertOk()->assertJsonStructure(['month', 'data']);
    ($this->auth)()->getJson('/api/v1/attendance/today')->assertOk()->assertJsonStructure(['data']);
});

it('lists leave types, balance and submits a request within balance', function (): void {
    ($this->auth)()->getJson('/api/v1/leave-types')->assertOk()->assertJsonStructure(['data']);
    ($this->auth)()->getJson('/api/v1/leave/balance')->assertOk()->assertJsonStructure(['year', 'data']);

    $type = LeaveType::where('code', 'TAHUNAN')->firstOrFail();

    ($this->auth)()->postJson('/api/v1/leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Urusan keluarga',
    ])->assertCreated();

    ($this->auth)()->getJson('/api/v1/leave')->assertOk()->assertJsonStructure(['data']);
});

it('rejects a leave request over the balance', function (): void {
    $type = LeaveType::where('code', 'PENTING')->firstOrFail(); // small quota, no negative

    ($this->auth)()->postJson('/api/v1/leave', [
        'leave_type_id' => $type->id,
        'start_date' => now()->addDays(1)->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'reason' => 'Kebanyakan',
    ])->assertStatus(422);
});

it('submits overtime, permission, wfh and claim', function (): void {
    ($this->auth)()->postJson('/api/v1/overtime', ['date' => now()->toDateString(), 'hours' => 2, 'reason' => 'Deadline'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/permissions', ['date' => now()->toDateString(), 'type' => 'keluar', 'start_time' => '10:00', 'end_time' => '11:00', 'reason' => 'Dokter'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/wfh', ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(), 'reason' => 'Remote'])->assertCreated();
    ($this->auth)()->postJson('/api/v1/claims', [
        'claim_type' => 'transport', 'title' => 'Grab ke klien', 'amount' => 85000,
        'claim_date' => now()->toDateString(), 'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertCreated();
});

it('lists payslips, announcements and notifications', function (): void {
    ($this->auth)()->getJson('/api/v1/payslips')->assertOk()->assertJsonStructure(['data']);
    ($this->auth)()->getJson('/api/v1/announcements')->assertOk()->assertJsonStructure(['data']);
    ($this->auth)()->getJson('/api/v1/notifications')->assertOk()->assertJsonStructure(['unread', 'data']);
});

it('updates the profile', function (): void {
    ($this->auth)()->putJson('/api/v1/profile', ['phone' => '0812-0000-0000', 'address' => 'Jl. Baru 1'])
        ->assertOk();
});

it('forbids a non-employee user (admin) from ESS endpoints', function (): void {
    $token = $this->postJson('/api/v1/login', ['email' => 'admin@avanahr.co.id', 'password' => 'password'])->json('token');
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/attendance')
        ->assertForbidden();
});
