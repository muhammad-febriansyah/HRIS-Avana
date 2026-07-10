<?php

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
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

    $this->manager = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->manager->tenant_id;

    $this->sub = Employee::forTenant($tenantId)
        ->where('id', '!=', $this->manager->id)
        ->where('status', 'active')
        ->firstOrFail();
    $this->sub->update(['manager_id' => $this->manager->id]);

    $this->makeCorrection = function (array $overrides = []) use ($tenantId): AttendanceCorrection {
        return AttendanceCorrection::create(array_merge([
            'tenant_id' => $tenantId,
            'employee_id' => $this->sub->id,
            'date' => '2026-07-05',
            'correction_type' => 'manual',
            'requested_clock_in' => '08:00',
            'requested_clock_out' => '17:00',
            'reason' => 'Lupa clock in',
            'current_approver_id' => $this->manager->id,
            'status' => 'pending',
        ], $overrides));
    };
});

it('submits an attendance correction', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/me/attendance/corrections', [
            'date' => '2026-07-01',
            'requested_clock_in' => '08:15',
            'reason' => 'Lupa absen masuk',
        ])
        ->assertCreated();

    $correction = AttendanceCorrection::where('employee_id', $this->manager->id)
        ->latest('id')
        ->firstOrFail();

    expect($correction->status)->toBe('pending');
    expect($correction->date->toDateString())->toBe('2026-07-01');
    expect($correction->current_approver_id)->toBe($this->manager->manager_id);
});

it('rejects a correction with no times', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/me/attendance/corrections', [
            'date' => '2026-07-01',
            'reason' => 'Tanpa jam',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['requested_clock_in', 'requested_clock_out']);
});

it('rejects a future-dated correction', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/me/attendance/corrections', [
            'date' => now()->addDay()->toDateString(),
            'requested_clock_in' => '08:00',
            'reason' => 'Besok',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['date']);
});

it('lists a correction in the manager approvals as a koreksi item', function (): void {
    $correction = ($this->makeCorrection)();

    ($this->auth)()
        ->getJson('/api/v1/mss/approvals')
        ->assertOk()
        ->assertJsonFragment(['id' => 'koreksi-'.$correction->id])
        ->assertJsonFragment(['type_label' => 'Koreksi Absen']);
});

it('writes attendance when a correction is approved and no record exists', function (): void {
    $correction = ($this->makeCorrection)();

    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/koreksi-'.$correction->id.'/act', ['action' => 'approve'])
        ->assertOk();

    $attendance = $correction->fresh()->attendance;

    expect($attendance)->not->toBeNull();
    expect($attendance->clock_in_at->format('H:i'))->toBe('08:00');
    expect($attendance->clock_out_at->format('H:i'))->toBe('17:00');
    expect($attendance->work_minutes)->toBe(540);
    expect($attendance->status)->toBe('present');

    $fresh = $correction->fresh();
    expect($fresh->status)->toBe('approved');
    expect($fresh->approver_id)->toBe($this->manager->user_id);
    expect($fresh->attendance_id)->toBe($attendance->id);
});

it('updates an existing attendance record when a correction is approved', function (): void {
    $attendance = Attendance::create([
        'tenant_id' => $this->manager->tenant_id,
        'employee_id' => $this->sub->id,
        'branch_id' => $this->sub->branch_id,
        'date' => '2026-07-05',
        'clock_in_at' => '2026-07-05 09:30:00',
        'status' => 'late',
    ]);

    $correction = ($this->makeCorrection)([
        'attendance_id' => $attendance->id,
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
    ]);

    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/koreksi-'.$correction->id.'/act', ['action' => 'approve'])
        ->assertOk();

    $attendance->refresh();
    expect($attendance->clock_in_at->format('H:i'))->toBe('08:00');
    expect($attendance->clock_out_at->format('H:i'))->toBe('17:00');
    expect($attendance->status)->toBe('present');
});

it('does not touch attendance when a correction is rejected', function (): void {
    $correction = ($this->makeCorrection)();

    ($this->auth)()
        ->postJson('/api/v1/mss/approvals/koreksi-'.$correction->id.'/act', ['action' => 'reject'])
        ->assertOk();

    expect($correction->fresh()->status)->toBe('rejected');
    expect(Attendance::where('employee_id', $this->sub->id)->whereDate('date', '2026-07-05')->exists())->toBeFalse();
});
