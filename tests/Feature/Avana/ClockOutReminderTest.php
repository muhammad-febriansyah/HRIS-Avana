<?php

use App\Console\Commands\RemindClockOut;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\UserDevice;
use App\Support\TenantTime;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employee = Employee::whereNotNull('user_id')->where('status', 'active')->orderBy('id')->firstOrFail();
    $this->zone = TenantTime::zone($this->employee->tenant_id);

    Attendance::where('employee_id', $this->employee->id)->delete();

    $this->openDay = function (Shift $shift, string $date, string $clockIn): Attendance {
        return Attendance::create([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'shift_id' => $shift->id,
            'date' => $date,
            'clock_in_at' => Carbon::parse($date.' '.$clockIn, $this->zone),
            'status' => 'present',
        ]);
    };

    $this->shift = fn (string $start, string $end): Shift => Shift::create([
        'tenant_id' => $this->employee->tenant_id,
        'code' => 'T-'.$start.$end, 'name' => 'Shift '.$start,
        'start_time' => $start, 'end_time' => $end,
        'late_tolerance_minutes' => 0, 'status' => 'active',
    ]);

    $this->dueAt = fn (string $at) => app(RemindClockOut::class)
        ->dueAttendances(Carbon::parse($at, $this->zone))
        ->pluck('employee_id')
        ->all();
});

it('reminds half an hour after the shift ended, not before', function (): void {
    ($this->openDay)(($this->shift)('07:00:00', '17:00:00'), '2026-08-10', '06:50');

    expect(($this->dueAt)('2026-08-10 17:20'))->toBe([])
        ->and(($this->dueAt)('2026-08-10 17:30'))->toBe([$this->employee->id])
        // Long past: the next shift owns the day now.
        ->and(($this->dueAt)('2026-08-10 23:30'))->toBe([]);
});

it('waits for a night shift to end on the next calendar day', function (): void {
    ($this->openDay)(($this->shift)('15:00:00', '01:00:00'), '2026-08-10', '14:55');

    expect(($this->dueAt)('2026-08-10 23:30'))->toBe([])
        ->and(($this->dueAt)('2026-08-11 01:30'))->toBe([$this->employee->id]);
});

it('leaves a day that was clocked out, or never rostered, alone', function (): void {
    $closed = ($this->openDay)(($this->shift)('07:00:00', '17:00:00'), '2026-08-10', '06:50');
    $closed->update(['clock_out_at' => Carbon::parse('2026-08-10 17:01', $this->zone)]);

    Attendance::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'date' => '2026-08-11',
        'clock_in_at' => Carbon::parse('2026-08-11 08:00', $this->zone),
        'status' => 'present',
    ]);

    expect(($this->dueAt)('2026-08-10 17:30'))->toBe([])
        ->and(($this->dueAt)('2026-08-11 20:00'))->toBe([]);
});

it('pushes the reminder once per open day', function (): void {
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    $credentials = tempnam(sys_get_temp_dir(), 'fcm');
    file_put_contents($credentials, json_encode([
        'project_id' => 'test-project',
        'client_email' => 'push@test-project.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ]));
    config(['services.firebase.credentials' => $credentials]);

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        'fcm.googleapis.com/*' => Http::response(['name' => 'sent']),
    ]);

    UserDevice::where('user_id', $this->employee->user_id)->delete();
    UserDevice::create([
        'user_id' => $this->employee->user_id,
        'device_id' => 'reminder-phone',
        'status' => 'active',
        'fcm_token' => 'fcm-reminder',
    ]);

    ($this->openDay)(($this->shift)('07:00:00', '17:00:00'), '2026-08-10', '06:50');

    $this->travelTo(Carbon::parse('2026-08-10 17:31', $this->zone));
    artisan('avana:remind-clock-out')->assertSuccessful();

    $this->travelTo(Carbon::parse('2026-08-10 17:46', $this->zone));
    artisan('avana:remind-clock-out')->assertSuccessful();

    $pushes = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), 'fcm.googleapis.com'))
        ->map(fn (array $pair): Request => $pair[0]);

    expect($pushes)->toHaveCount(1)
        ->and($pushes->first()['message']['token'])->toBe('fcm-reminder')
        ->and($pushes->first()['message']['notification']['title'])->toBe('Jangan lupa absen pulang');

    unlink($credentials);
});
