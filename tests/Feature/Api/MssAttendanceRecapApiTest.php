<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    // The recap ends at today, so rows planted on the 2nd, 3rd and 4th of the
    // month only count when the month is already past them. Run from late in
    // the month and the fixture is always behind the clock, whatever the real
    // date happens to be.
    Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays(27)->setTime(12, 0));

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

    // Start from a clean slate: the demo seeder plants attendance for this month.
    Attendance::where('employee_id', $this->sub->id)->delete();

    $make = function (string $date, string $status, int $work = 0, int $late = 0) use ($tenantId): void {
        Attendance::create([
            'tenant_id' => $tenantId,
            'employee_id' => $this->sub->id,
            'branch_id' => $this->sub->branch_id,
            'date' => $date,
            'status' => $status,
            'work_minutes' => $work,
            'late_minutes' => $late,
        ]);
    };

    $month = now()->startOfMonth();
    $make($month->copy()->addDays(1)->toDateString(), 'present', 480);
    $make($month->copy()->addDays(2)->toDateString(), 'late', 450, 30);
    $make($month->copy()->addDays(3)->toDateString(), 'late', 460, 15);
    $make($month->copy()->addDays(4)->toDateString(), 'absent');
    // A row outside the current month must be excluded from the default range.
    $make($month->copy()->subMonth()->toDateString(), 'present', 480);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('recaps team attendance for the current month by default', function (): void {
    $res = ($this->auth)()
        ->getJson('/api/v1/mss/attendance/recap')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'present', 'late', 'absent', 'leave', 'wfh', 'holiday', 'work_hours', 'late_minutes']],
            'meta' => ['start', 'end', 'summary' => ['members', 'present', 'late', 'absent', 'work_hours', 'late_minutes']],
        ]);

    $row = collect($res->json('data'))->firstWhere('id', $this->sub->id);

    expect($row['present'])->toBe(1);
    expect($row['late'])->toBe(2);
    expect($row['absent'])->toBe(1);
    expect($row['work_hours'])->toBe(23.2); // (480+450+460)/60
    expect($row['late_minutes'])->toBe(45);
});

it('honours an explicit period filter', function (): void {
    $start = now()->startOfMonth()->addDays(3)->toDateString();
    $end = now()->startOfMonth()->addDays(4)->toDateString();

    $res = ($this->auth)()
        ->getJson('/api/v1/mss/attendance/recap?start='.$start.'&end='.$end)
        ->assertOk();

    $row = collect($res->json('data'))->firstWhere('id', $this->sub->id);

    expect($row['present'])->toBe(0);
    expect($row['late'])->toBe(1);
    expect($row['absent'])->toBe(1);
});

it('rejects an end date before the start date', function (): void {
    ($this->auth)()
        ->getJson('/api/v1/mss/attendance/recap?start=2026-07-10&end=2026-07-01')
        ->assertStatus(422)
        ->assertJsonValidationErrors('end');
});

it('exports the team recap as a csv', function (): void {
    $res = ($this->auth)()->get('/api/v1/mss/attendance/recap/export');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');
    expect($res->headers->get('content-disposition'))->toContain('rekap-absensi-tim');
    $csv = $res->streamedContent();
    expect($csv)->toContain('Hadir');
    expect($csv)->toContain('Terlambat');
    expect($csv)->toContain($this->sub->employee_number.',1,2,1,0,0,0,0,23.2,45');
});
