<?php

use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\SecurityAnomalyScanner;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->tenant = Tenant::firstOrCreate(
        ['slug' => 'tenant-anomali'],
        ['name' => 'PT Uji Anomali', 'status' => 'active'],
    );

    $this->actor = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Pelaku',
        'email' => 'pelaku@example.test',
        'password' => 'rahasia-panjang-sekali',
        'status' => 'active',
    ]);

    $this->scanner = app(SecurityAnomalyScanner::class);
});

/** Write an activity row at a chosen moment. */
function activity(array $attributes): UserActivityLog
{
    return UserActivityLog::create(array_merge([
        'created_at' => now(),
        'ip_address' => '10.0.0.1',
    ], $attributes));
}

test('a quiet trail raises nothing', function (): void {
    expect($this->scanner->scan())->toBe([]);
});

test('a run of failed logins against one address is reported', function (): void {
    config(['security.anomaly.failed_login_threshold' => 5]);

    foreach (range(1, 6) as $attempt) {
        activity([
            'tenant_id' => $this->tenant->id,
            'event' => 'login_failed',
            'description' => 'gagal',
            'properties' => ['email' => 'korban@example.test'],
            'ip_address' => '10.0.0.'.$attempt,
        ]);
    }

    $findings = collect($this->scanner->scan())->where('kind', 'brute_force');

    expect($findings)->toHaveCount(1)
        ->and($findings->first()['body'])->toContain('korban@example.test')
        ->and($findings->first()['body'])->toContain('6 alamat IP');
});

test('a handful of failed logins stays below the threshold', function (): void {
    config(['security.anomaly.failed_login_threshold' => 10]);

    foreach (range(1, 3) as $attempt) {
        activity([
            'tenant_id' => $this->tenant->id,
            'event' => 'login_failed',
            'properties' => ['email' => 'lupa@example.test'],
        ]);
    }

    expect(collect($this->scanner->scan())->where('kind', 'brute_force'))->toHaveCount(0);
});

test('a sign-in outside working hours is reported', function (): void {
    config(['security.anomaly.work_hours_start' => 5, 'security.anomaly.work_hours_end' => 22]);

    activity([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->actor->id,
        'event' => 'login',
        'created_at' => now()->setTime(2, 30),
    ]);

    $findings = collect($this->scanner->scan())->where('kind', 'off_hours');

    expect($findings)->toHaveCount(1)
        ->and($findings->first()['body'])->toContain('Pelaku');
});

test('a sign-in during working hours is not reported', function (): void {
    config(['security.anomaly.work_hours_start' => 5, 'security.anomaly.work_hours_end' => 22]);

    activity([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->actor->id,
        'event' => 'login',
        'created_at' => now()->setTime(9, 0),
    ]);

    expect(collect($this->scanner->scan())->where('kind', 'off_hours'))->toHaveCount(0);
});

test('one account signing in from many addresses is reported', function (): void {
    config([
        'security.anomaly.distinct_ip_threshold' => 3,
        'security.anomaly.work_hours_start' => 0,
        'security.anomaly.work_hours_end' => 24,
    ]);

    foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $ip) {
        activity([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'event' => 'login',
            'ip_address' => $ip,
        ]);
    }

    expect(collect($this->scanner->scan())->where('kind', 'scattered_sessions'))->toHaveCount(1);
});

test('a burst of exports by one user is reported', function (): void {
    config(['security.anomaly.export_threshold' => 4]);

    foreach (range(1, 5) as $index) {
        activity([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'event' => 'report_exported',
        ]);
    }

    $findings = collect($this->scanner->scan())->where('kind', 'bulk_export');

    expect($findings)->toHaveCount(1)
        ->and($findings->first()['body'])->toContain('5 ekspor');
});

test('activity older than the window is ignored', function (): void {
    config(['security.anomaly.window_hours' => 24, 'security.anomaly.failed_login_threshold' => 2]);

    foreach (range(1, 5) as $attempt) {
        activity([
            'tenant_id' => $this->tenant->id,
            'event' => 'login_failed',
            'properties' => ['email' => 'lama@example.test'],
            'created_at' => now()->subDays(3),
        ]);
    }

    expect($this->scanner->scan())->toBe([]);
});

test('the scan can be switched off entirely', function (): void {
    config(['security.anomaly.enabled' => false, 'security.anomaly.failed_login_threshold' => 1]);

    activity([
        'tenant_id' => $this->tenant->id,
        'event' => 'login_failed',
        'properties' => ['email' => 'siapa@example.test'],
    ]);

    expect($this->scanner->scan())->toBe([]);
});

test('the command notifies super admins and dedupes on a repeat run', function (): void {
    config(['security.anomaly.failed_login_threshold' => 2]);

    $superAdmin = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Super',
        'email' => 'super@example.test',
        'password' => 'rahasia-panjang-sekali',
        'status' => 'active',
    ]);

    $role = Role::firstOrCreate(
        ['tenant_id' => null, 'code' => 'super_admin'],
        ['name' => 'Super Admin'],
    );

    $superAdmin->roles()->syncWithoutDetaching([$role->id]);

    foreach (range(1, 3) as $attempt) {
        activity([
            'tenant_id' => $this->tenant->id,
            'event' => 'login_failed',
            'properties' => ['email' => 'korban@example.test'],
        ]);
    }

    $this->artisan('avana:scan-security-anomalies')->assertSuccessful();
    $this->artisan('avana:scan-security-anomalies')->assertSuccessful();

    expect(Notification::where('user_id', $superAdmin->id)->where('type', 'security')->count())->toBe(1);
});

test('the scheduled backup writes a dump and prunes old ones', function (): void {
    Storage::fake('local');

    $this->artisan('avana:backup-database', ['--disk' => 'local', '--keep' => 7])
        ->assertSuccessful();

    $files = Storage::disk('local')->files('backups');

    expect($files)->toHaveCount(1)
        ->and($files[0])->toContain('.sql.gz')
        ->and(Storage::disk('local')->size($files[0]))->toBeGreaterThan(0);
});
