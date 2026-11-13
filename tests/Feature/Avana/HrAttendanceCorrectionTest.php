<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Role;
use App\Models\SalaryMaster;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\AbsenceMarker;
use App\Support\TenantTime;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/** A Wednesday in the past, so it may be corrected. */
const HR_FIX_DATE = '2026-11-11';

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->travelTo(Carbon::parse('2026-11-13 10:00', TenantTime::zone(1)));

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    $this->worker = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('status', 'active')
        ->orderBy('id')
        ->firstOrFail();
    $this->worker->update(['join_date' => '2020-01-01', 'resign_date' => null, 'attendance_exempt' => false]);

    Attendance::where('employee_id', $this->worker->id)->whereDate('date', HR_FIX_DATE)->delete();
    ShiftSchedule::where('employee_id', $this->worker->id)->whereDate('date', HR_FIX_DATE)->delete();

    $this->shift = Shift::create([
        'tenant_id' => $this->tenant->id, 'code' => 'HR-FIX', 'name' => 'Kantor',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'late_tolerance_minutes' => 10, 'work_days' => [1, 2, 3, 4, 5], 'status' => 'active',
    ]);
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->worker->id,
        'date' => HR_FIX_DATE, 'shift_id' => $this->shift->id,
    ]);
});

function workerDay(object $ctx): ?Attendance
{
    return Attendance::where('employee_id', $ctx->worker->id)->whereDate('date', HR_FIX_DATE)->first();
}

function correctDay(object $ctx, array $payload): TestResponse
{
    return actingAs($ctx->admin)->post(route('avana.absensi.koreksi'), array_merge([
        'employee_id' => $ctx->worker->id,
        'date' => HR_FIX_DATE,
        'reason' => 'Lupa absen, dikonfirmasi atasan',
    ], $payload));
}

it('turns an Alpa into Hadir from the clock times and leaves a trail', function (): void {
    Attendance::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->worker->id,
        'date' => HR_FIX_DATE, 'status' => 'absent',
    ]);

    correctDay($this, ['status' => 'hadir', 'clock_in' => '07:55', 'clock_out' => '17:05'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $day = workerDay($this);

    expect($day->status)->toBe('present')
        ->and($day->clock_in_at->format('H:i'))->toBe('07:55')
        ->and($day->work_minutes)->toBeGreaterThan(0)
        ->and(Attendance::where('employee_id', $this->worker->id)->whereDate('date', HR_FIX_DATE)->count())->toBe(1);

    $correction = AttendanceCorrection::where('attendance_id', $day->id)->latest('id')->firstOrFail();
    expect($correction->status)->toBe('approved')
        ->and($correction->correction_type)->toBe('hr_hadir')
        ->and((int) $correction->approver_id)->toBe($this->admin->id);

    $log = UserActivityLog::where('event', 'attendance.hr_correction')->latest('id')->firstOrFail();
    expect($log->properties['before']['status'])->toBe('absent')
        ->and($log->properties['after']['status'])->toBe('present')
        ->and($log->properties['reason'])->toBe('Lupa absen, dikonfirmasi atasan');
});

it('judges a late clock-in against the rostered shift', function (): void {
    correctDay($this, ['status' => 'hadir', 'clock_in' => '08:40'])->assertSessionHas('success');

    expect(workerDay($this))
        ->status->toBe('late')
        ->late_minutes->toBeGreaterThan(0);
});

it('marks a day with no record as Cuti/Izin or Alpa', function (string $status): void {
    correctDay($this, ['status' => $status])->assertSessionHas('success');

    expect(workerDay($this))
        ->not->toBeNull()
        ->status->toBe($status)
        ->shift_id->toBe($this->shift->id);
})->with(['leave', 'absent']);

it('rejects an incomplete or future correction', function (array $payload, string $field): void {
    correctDay($this, $payload)->assertSessionHasErrors($field);

    expect(workerDay($this))->toBeNull();
})->with([
    'hadir without clock-in' => [['status' => 'hadir'], 'clock_in'],
    'no reason' => [['status' => 'absent', 'reason' => ''], 'reason'],
    'tomorrow' => [['status' => 'absent', 'date' => '2026-11-14'], 'date'],
    'unknown status' => [['status' => 'present'], 'status'],
]);

it('forbids staff without the correction permission', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->post(route('avana.absensi.koreksi'), [
            'employee_id' => $this->worker->id, 'date' => HR_FIX_DATE,
            'status' => 'absent', 'reason' => 'x',
        ])
        ->assertForbidden();

    expect(workerDay($this))->toBeNull();
});

it('lets HR mark a past no-show as Alpa straight from the rekap row', function (): void {
    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => HR_FIX_DATE, 'status' => 'not_in', 'per_page' => 500]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendances.data', fn ($rows): bool => collect($rows)->contains('id', -$this->worker->id)));

    correctDay($this, ['status' => 'absent'])->assertSessionHas('success');

    expect(workerDay($this)?->status)->toBe('absent');
});

it('offers the correction column to HR on the rekap', function (): void {
    actingAs($this->admin)
        ->get(route('avana.absensi', ['date' => HR_FIX_DATE]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_correct', true)
            ->where('today', '2026-11-13'));
});

describe('tidak wajib absen', function (): void {
    beforeEach(function (): void {
        $feature = Feature::firstOrCreate(['code' => 'attendance'], ['name' => 'Attendance', 'is_active' => true]);
        $feature->update(['is_active' => true]);
        TenantFeature::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'feature_id' => $feature->id],
            ['is_enabled' => true],
        );
    });

    it('keeps an exempt employee out of the Alpa marker and Belum Absen', function (): void {
        actingAs($this->admin)
            ->post(route('avana.absensi.kebijakan.tidak-wajib.store'), ['employee_ids' => [$this->worker->id]])
            ->assertSessionHas('success');

        expect($this->worker->fresh()->attendance_exempt)->toBeTrue();

        AbsenceMarker::markTenant($this->tenant->id, Carbon::parse(HR_FIX_DATE));
        expect(workerDay($this))->toBeNull()
            ->and(AbsenceMarker::missing($this->tenant->id, Carbon::parse(HR_FIX_DATE))->pluck('id'))
            ->not->toContain($this->worker->id);

        actingAs($this->admin)
            ->get(route('avana.absensi.kebijakan'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('exemptEmployees.list', fn ($list): bool => collect($list)->contains('id', $this->worker->id))
                ->where('exemptEmployees.candidates', fn ($list): bool => ! collect($list)->contains('id', $this->worker->id)));

        actingAs($this->admin)
            ->delete(route('avana.absensi.kebijakan.tidak-wajib.destroy', $this->worker->fresh()))
            ->assertSessionHas('success');

        AbsenceMarker::markTenant($this->tenant->id, Carbon::parse(HR_FIX_DATE));
        expect(workerDay($this)?->status)->toBe('absent');
    });

    it('lets only HR admins change the list', function (): void {
        $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
        $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $staff->roles()->sync([$employeeRole->id]);

        actingAs($staff)
            ->post(route('avana.absensi.kebijakan.tidak-wajib.store'), ['employee_ids' => [$this->worker->id]])
            ->assertForbidden();

        expect($this->worker->fresh()->attendance_exempt)->toBeFalse();
    });
});

describe('payroll follows the correction', function (): void {
    beforeEach(function (): void {
        Route::middleware('web')->prefix('spec-hr-fix')->group(function (): void {
            Route::post('payroll/run', [PayrollController::class, 'run']);
        });

        $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
        $this->payee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();
        $this->payee->update(['payday_id' => null, 'join_date' => '2020-01-01', 'resign_date' => null]);

        $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
        giveMasterComponent($this->payee, $basic, 6_000_000);
        SalaryMaster::whereKey($this->payee->fresh()->salary_master_id)->update([
            'day_divisor' => 20,
            'day_calc_method_id' => null,
            'attendance_start_day' => null,
            'attendance_end_day' => null,
        ]);

        $this->payDate = $this->period->start_date->copy()->addDays(2)->toDateString();
        Attendance::where('employee_id', $this->payee->id)
            ->whereBetween('date', [$this->period->start_date->toDateString(), $this->period->end_date->toDateString()])
            ->delete();
    });

    function fixPayee(object $ctx, array $payload): TestResponse
    {
        return actingAs($ctx->admin)->post(route('avana.absensi.koreksi'), array_merge([
            'employee_id' => $ctx->payee->id,
            'date' => $ctx->payDate,
            'reason' => 'Koreksi HR',
        ], $payload));
    }

    function runPayeePayroll(object $ctx): PayrollRunItem
    {
        actingAs($ctx->admin)->post('spec-hr-fix/payroll/run')->assertSessionHas('success');

        $run = PayrollRun::forTenant($ctx->tenant->id)
            ->where('payroll_period_id', $ctx->period->id)
            ->current()
            ->latest('id')
            ->firstOrFail();

        return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->payee->id)->firstOrFail();
    }

    function alpaLine(PayrollRunItem $item): ?array
    {
        return collect($item->calculation_snapshot['deductions'])
            ->first(fn (array $row): bool => str_starts_with($row['name'], 'Potongan Alpa'));
    }

    it('docks an Alpa HR set, and gives it back once HR turns it into Hadir', function (): void {
        fixPayee($this, ['status' => 'absent'])->assertSessionHas('success');

        $docked = runPayeePayroll($this);
        $line = alpaLine($docked);

        expect($line)->not->toBeNull()
            ->and($line['name'])->toBe('Potongan Alpa (1 hari)')
            ->and((float) $line['amount'])->toBe(round((float) $docked->calculation_snapshot['absence']['daily_rate']))
            ->and((float) $docked->calculation_snapshot['absence']['daily_rate'] * 20)->toBeGreaterThan(0.0);

        fixPayee($this, ['status' => 'hadir', 'clock_in' => '08:00', 'clock_out' => '17:00'])->assertSessionHas('success');

        $restored = runPayeePayroll($this);

        expect(alpaLine($restored))->toBeNull()
            ->and($restored->calculation_snapshot['absence'])->toBeNull()
            ->and((float) $restored->net_salary)->toBeGreaterThan((float) $docked->net_salary);
    });

    it('does not dock leave HR recorded', function (): void {
        fixPayee($this, ['status' => 'leave'])->assertSessionHas('success');

        expect(alpaLine(runPayeePayroll($this)))->toBeNull();
    });

    it('warns HR when the day sits in a locked payroll period', function (): void {
        $this->period->update(['status' => 'locked']);

        fixPayee($this, ['status' => 'absent'])
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn (string $message): bool => str_contains($message, 'sudah dikunci'));
    });
});
