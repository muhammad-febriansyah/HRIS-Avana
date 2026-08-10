<?php

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\EmployeeFaceEmbedding;
use App\Models\FaceScanLog;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee()->firstOrFail();

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

it('records a batch of device-side scan diagnostics', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/face/log', [
            'events' => [
                [
                    'context' => 'enroll',
                    'outcome' => 'fail',
                    'reason' => 'no_face',
                    'step' => 0,
                    'message' => 'Pastikan hanya wajah Anda yang terlihat',
                    'metrics' => ['faces' => 0, 'frame_width' => 480, 'frame_height' => 640],
                ],
                [
                    'context' => 'verify',
                    'outcome' => 'ok',
                    'reason' => 'captured',
                    'metrics' => ['yaw' => -3.456789, 'embedding_dimensions' => 192],
                ],
            ],
            'device' => [
                'platform' => 'ios',
                'os_version' => 'iOS 18.5',
                'model' => 'iPhone14,5',
                'app_version' => '1.0.0',
                'device_id' => 'abc123',
            ],
        ])
        ->assertStatus(202);

    $logs = FaceScanLog::where('employee_id', $this->employee->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(2);
    expect($logs[0]->reason)->toBe('no_face');
    expect($logs[0]->step)->toBe(0);
    expect($logs[0]->metrics['faces'])->toBe(0);
    expect($logs[0]->platform)->toBe('ios');
    expect($logs[0]->device_model)->toBe('iPhone14,5');
    expect($logs[1]->outcome)->toBe('ok');
    expect($logs[1]->metrics['yaw'])->toBe(-3.4568);
});

it('rejects unknown contexts and outcomes', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/face/log', [
            'events' => [['context' => 'bogus', 'outcome' => 'maybe', 'reason' => 'no_face']],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['events.0.context', 'events.0.outcome']);

    expect(FaceScanLog::count())->toBe(0);
});

it('drops metric keys it does not know about', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/face/log', [
            'events' => [[
                'context' => 'verify',
                'outcome' => 'fail',
                'reason' => 'not_frontal',
                'metrics' => ['yaw' => 30, 'secret_note' => 'should not be stored'],
            ]],
        ])
        ->assertStatus(202);

    $metrics = FaceScanLog::firstOrFail()->metrics;

    expect($metrics)->toHaveKey('yaw');
    expect($metrics)->not->toHaveKey('secret_note');
});

it('logs the enrollment that succeeded', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/face/enroll', ['embedding' => array_fill(0, 192, 0.1)])
        ->assertOk();

    $log = FaceScanLog::where('context', FaceScanLog::CONTEXT_ENROLL)->firstOrFail();

    expect($log->reason)->toBe('enrolled');
    expect($log->outcome)->toBe('ok');
    expect($log->metrics['embedding_dimensions'])->toBe(192);
});

it('logs the server-side match verdict when clocking in', function (): void {
    AttendancePolicy::resolve($this->employee->tenant_id)->forceFill([
        'face_mode' => AttendancePolicy::FACE_MODE_RECOGNITION,
        'require_face_enrollment' => true,
        'require_liveness_challenge' => false,
    ])->save();

    $embedding = array_fill(0, 192, 0.1);

    EmployeeFaceEmbedding::updateOrCreate(
        ['employee_id' => $this->employee->id],
        [
            'tenant_id' => $this->employee->tenant_id,
            'embedding' => $embedding,
            'dimensions' => 192,
            'enrolled_at' => now(),
        ],
    );

    Attendance::where('employee_id', $this->employee->id)->delete();

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    // A vector pointing the other way can never clear the cosine threshold.
    ($this->auth)($token)
        ->postJson('/api/v1/me/attendance/clock', [
            'type' => 'in',
            'face_embedding' => array_fill(0, 192, -0.1),
        ]);

    $log = FaceScanLog::where('context', FaceScanLog::CONTEXT_CLOCK)->latest('id')->firstOrFail();

    expect($log->reason)->toBe('face_mismatch');
    expect($log->metrics)->toHaveKey('score');
    expect($log->metrics['threshold'])->toBeFloat();
});

it('shows the log to an admin and hides it from an employee', function (): void {
    $this->withoutVite();

    FaceScanLog::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'context' => FaceScanLog::CONTEXT_ENROLL,
        'outcome' => 'fail',
        'reason' => 'no_face',
        'metrics' => ['faces' => 0],
        'platform' => 'ios',
    ]);

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($admin)
        ->get('/avana/absensi/log-wajah')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/absensi-log/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.reason', 'no_face')
            ->where('logs.data.0.reason_label', 'Wajah tidak terdeteksi')
            ->where('summary.by_platform.ios.fail', 1)
        );

    actingAs($this->employeeUser)
        ->get('/avana/absensi/log-wajah')
        ->assertForbidden();
});

it('filters the log by platform', function (): void {
    $this->withoutVite();

    foreach (['ios', 'android'] as $platform) {
        FaceScanLog::create([
            'tenant_id' => $this->employee->tenant_id,
            'employee_id' => $this->employee->id,
            'context' => FaceScanLog::CONTEXT_VERIFY,
            'outcome' => 'fail',
            'reason' => 'no_face',
            'platform' => $platform,
        ]);
    }

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    actingAs($admin)
        ->get('/avana/absensi/log-wajah?platform=ios')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.platform', 'ios')
        );
});
