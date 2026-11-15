<?php

use App\Http\Controllers\Avana\LaporanController;
use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\Feature;
use App\Models\LeaveRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryMaster;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Services\AbsenceMarker;
use App\Support\AttendanceFines;
use App\Support\TenantTime;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

/** A Wednesday, so the plain office week expects everyone. */
const ABSENCE_WEDNESDAY = '2026-11-11';

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    $this->alice = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('status', 'active')
        ->orderBy('id')
        ->firstOrFail();
    $this->alice->update(['join_date' => '2020-01-01', 'resign_date' => null]);

    setAttendanceFeature($this->tenant, true);
    clearAttendance($this->alice, ABSENCE_WEDNESDAY);
});

function setAttendanceFeature(Tenant $tenant, bool $enabled): void
{
    $feature = Feature::firstOrCreate(['code' => 'attendance'], ['name' => 'Attendance', 'is_active' => true]);
    $feature->update(['is_active' => true]);

    TenantFeature::updateOrCreate(
        ['tenant_id' => $tenant->id, 'feature_id' => $feature->id],
        ['is_enabled' => $enabled],
    );
}

function clearAttendance(Employee $employee, string $date): void
{
    Attendance::where('employee_id', $employee->id)->whereDate('date', $date)->delete();
    LeaveRequest::where('employee_id', $employee->id)->delete();
    ShiftSchedule::where('employee_id', $employee->id)->whereDate('date', $date)->delete();
}

function aliceRowOn(object $ctx, string $date): ?Attendance
{
    return Attendance::where('employee_id', $ctx->alice->id)->whereDate('date', $date)->first();
}

it('marks a due employee who never clocked in as Alpa', function (): void {
    AbsenceMarker::markTenant($this->tenant->id, Carbon::parse(ABSENCE_WEDNESDAY));

    expect(aliceRowOn($this, ABSENCE_WEDNESDAY))
        ->not->toBeNull()
        ->status->toBe('absent')
        ->clock_in_at->toBeNull();
});

it('leaves a day that already has a record alone', function (): void {
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => ABSENCE_WEDNESDAY,
        'status' => 'present',
        'clock_in_at' => ABSENCE_WEDNESDAY.' 08:00:00',
    ]);

    AbsenceMarker::markTenant($this->tenant->id, Carbon::parse(ABSENCE_WEDNESDAY));

    expect(Attendance::where('employee_id', $this->alice->id)->whereDate('date', ABSENCE_WEDNESDAY)->count())->toBe(1)
        ->and(aliceRowOn($this, ABSENCE_WEDNESDAY)->status)->toBe('present');
});

it('does not mark a day the employee was not due', function (string $case): void {
    $date = $case === 'weekend' ? '2026-11-14' : ABSENCE_WEDNESDAY;
    clearAttendance($this->alice, $date);

    match ($case) {
        'weekend' => null,
        'holiday' => CalendarEvent::create([
            'tenant_id' => $this->tenant->id, 'title' => 'Libur', 'type' => 'holiday',
            'start_date' => ABSENCE_WEDNESDAY, 'end_date' => ABSENCE_WEDNESDAY,
        ]),
        'leave' => LeaveRequest::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id,
            'start_date' => ABSENCE_WEDNESDAY, 'end_date' => ABSENCE_WEDNESDAY,
            'total_days' => 1, 'status' => 'approved',
        ]),
        'rostered off' => ShiftSchedule::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id,
            'date' => ABSENCE_WEDNESDAY, 'shift_id' => null,
        ]),
        'not joined yet' => $this->alice->update(['join_date' => '2026-12-01']),
        'no login' => $this->alice->update(['user_id' => null]),
    };

    AbsenceMarker::markTenant($this->tenant->id, Carbon::parse($date));

    expect(aliceRowOn($this, $date))->toBeNull();
})->with(['weekend', 'holiday', 'leave', 'rostered off', 'not joined yet', 'no login']);

it('marks a weekend the roster put the employee on', function (): void {
    $saturday = '2026-11-14';
    clearAttendance($this->alice, $saturday);

    $shift = Shift::create([
        'tenant_id' => $this->tenant->id, 'code' => 'WE-SPEC', 'name' => 'Akhir Pekan',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'late_tolerance_minutes' => 10, 'work_days' => [6], 'status' => 'active',
    ]);
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id,
        'date' => $saturday, 'shift_id' => $shift->id,
    ]);

    AbsenceMarker::markTenant($this->tenant->id, Carbon::parse($saturday));

    expect(aliceRowOn($this, $saturday))
        ->status->toBe('absent')
        ->shift_id->toBe($shift->id);
});

it('marks nobody for a tenant that does not run attendance', function (): void {
    setAttendanceFeature($this->tenant, false);

    expect(AbsenceMarker::markTenant($this->tenant->id, Carbon::parse(ABSENCE_WEDNESDAY)))->toBe(0)
        ->and(aliceRowOn($this, ABSENCE_WEDNESDAY))->toBeNull();
});

it('marks a rostered no-show Alpa only once the shift has ended', function (): void {
    $zone = TenantTime::zone($this->tenant->id);
    $shift = Shift::create([
        'tenant_id' => $this->tenant->id, 'code' => 'END-SPEC', 'name' => 'Bengkel',
        'start_time' => '08:00:00', 'end_time' => '18:00:00',
        'late_tolerance_minutes' => 10, 'work_days' => [1, 2, 3, 4, 5], 'status' => 'active',
    ]);
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id,
        'date' => ABSENCE_WEDNESDAY, 'shift_id' => $shift->id,
    ]);

    // Mid-shift: late, perhaps, but not missing yet.
    $this->travelTo(Carbon::parse(ABSENCE_WEDNESDAY.' 14:55', $zone));
    artisan('avana:mark-absences', ['--tenant' => $this->tenant->id])->assertSuccessful();
    expect(aliceRowOn($this, ABSENCE_WEDNESDAY))->toBeNull();

    // The shift is over: Alpa, the same day, with the shift on the row.
    $this->travelTo(Carbon::parse(ABSENCE_WEDNESDAY.' 18:00', $zone));
    artisan('avana:mark-absences', ['--tenant' => $this->tenant->id])->assertSuccessful();
    expect(aliceRowOn($this, ABSENCE_WEDNESDAY))
        ->status->toBe('absent')
        ->shift_id->toBe($shift->id);
});

it('waits for a night shift to end on the next calendar day', function (): void {
    $zone = TenantTime::zone($this->tenant->id);
    $shift = Shift::create([
        'tenant_id' => $this->tenant->id, 'code' => 'NIGHT-SPEC', 'name' => 'Siang Sales',
        'start_time' => '15:00:00', 'end_time' => '01:00:00',
        'late_tolerance_minutes' => 0, 'work_days' => [1, 2, 3, 4, 5], 'status' => 'active',
    ]);
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->alice->id,
        'date' => ABSENCE_WEDNESDAY, 'shift_id' => $shift->id,
    ]);

    $this->travelTo(Carbon::parse('2026-11-12 00:30', $zone));
    AbsenceMarker::markEnded($this->tenant->id, TenantTime::now($this->tenant->id));
    expect(aliceRowOn($this, ABSENCE_WEDNESDAY))->toBeNull();

    $this->travelTo(Carbon::parse('2026-11-12 01:05', $zone));
    AbsenceMarker::markEnded($this->tenant->id, TenantTime::now($this->tenant->id));
    expect(aliceRowOn($this, ABSENCE_WEDNESDAY)?->status)->toBe('absent');
});

it('ends an unrostered office day at midnight', function (): void {
    $zone = TenantTime::zone($this->tenant->id);

    $this->travelTo(Carbon::parse(ABSENCE_WEDNESDAY.' 23:50', $zone));
    AbsenceMarker::markEnded($this->tenant->id, TenantTime::now($this->tenant->id));
    expect(aliceRowOn($this, ABSENCE_WEDNESDAY))->toBeNull();

    $this->travelTo(Carbon::parse('2026-11-12 00:05', $zone));
    AbsenceMarker::markEnded($this->tenant->id, TenantTime::now($this->tenant->id));
    expect(aliceRowOn($this, ABSENCE_WEDNESDAY)?->status)->toBe('absent');
});

it('closes the days a stopped scheduler skipped once it runs again', function (): void {
    $zone = TenantTime::zone($this->tenant->id);
    $thursday = '2026-11-12';
    $friday = '2026-11-13';
    clearAttendance($this->alice, $thursday);
    clearAttendance($this->alice, $friday);
    clearAttendance($this->alice, '2026-11-10');

    // No run since Tuesday; the first one back is Friday evening.
    $this->travelTo(Carbon::parse($friday.' 18:00', $zone));
    AbsenceMarker::markEnded($this->tenant->id, TenantTime::now($this->tenant->id));

    expect(aliceRowOn($this, ABSENCE_WEDNESDAY)?->status)->toBe('absent')
        ->and(aliceRowOn($this, $thursday)?->status)->toBe('absent')
        // Friday's office day only ends at midnight.
        ->and(aliceRowOn($this, $friday))->toBeNull()
        // Beyond the catch-up window is a deliberate backfill, not a sweep.
        ->and(aliceRowOn($this, '2026-11-10'))->toBeNull();
});

it('does not catch up a day inside a locked payroll period', function (): void {
    $zone = TenantTime::zone($this->tenant->id);
    $thursday = '2026-11-12';
    clearAttendance($this->alice, $thursday);

    PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'LOCK-TEST', 'name' => 'Locked',
        'start_date' => '2026-11-01', 'end_date' => ABSENCE_WEDNESDAY, 'status' => 'locked',
    ]);

    $this->travelTo(Carbon::parse('2026-11-13 08:00', $zone));
    AbsenceMarker::markEnded($this->tenant->id, TenantTime::now($this->tenant->id));

    expect(aliceRowOn($this, ABSENCE_WEDNESDAY))->toBeNull()
        ->and(aliceRowOn($this, $thursday)?->status)->toBe('absent');
});

it('backfills a range but never marks today', function (): void {
    $zone = TenantTime::zone($this->tenant->id);
    $thursday = '2026-11-12';
    clearAttendance($this->alice, $thursday);

    $this->travelTo(Carbon::parse($thursday.' 10:00', $zone));
    artisan('avana:mark-absences', [
        '--tenant' => $this->tenant->id,
        '--from' => ABSENCE_WEDNESDAY,
        '--to' => $thursday,
    ])->assertSuccessful();

    expect(aliceRowOn($this, ABSENCE_WEDNESDAY)?->status)->toBe('absent')
        ->and(aliceRowOn($this, $thursday))->toBeNull();
});

it('lets a late clock-in overturn the Alpa and drops its automatic penalty', function (): void {
    AbsenceMarker::markTenant($this->tenant->id, Carbon::parse(ABSENCE_WEDNESDAY));
    AttendanceFines::generate($this->tenant->id, ABSENCE_WEDNESDAY, ABSENCE_WEDNESDAY, $this->alice->id);

    $penalty = fn () => AttendancePenalty::where('employee_id', $this->alice->id)
        ->where('violation_type', 'absent')
        ->whereDate('date', ABSENCE_WEDNESDAY);
    expect($penalty()->exists())->toBeTrue();

    aliceRowOn($this, ABSENCE_WEDNESDAY)->update([
        'status' => 'present',
        'clock_in_at' => ABSENCE_WEDNESDAY.' 08:00:00',
    ]);
    AttendanceFines::generate($this->tenant->id, ABSENCE_WEDNESDAY, ABSENCE_WEDNESDAY, $this->alice->id);

    expect($penalty()->exists())->toBeFalse();
});

it('counts today\'s and a past day\'s no-shows on their own card', function (): void {
    clearAttendance($this->alice, '2026-11-10');
    $this->travelTo(Carbon::parse(ABSENCE_WEDNESDAY.' 10:00', TenantTime::zone($this->tenant->id)));

    actingAs($this->admin)
        ->get('/avana/absensi?date='.ABSENCE_WEDNESDAY)
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            // Today keeps a "belum absen" card so the cards add up to everyone
            // due; each one turns into a stored Alpa as their shift ends.
            ->where('kpis.belum_absen', fn ($count): bool => $count >= 1)
            ->where('kpis.belum_absen_label', 'Belum Absen (hari ini)')
            ->where('kpis.belum_absen_hint', 'Jadi Alpa setelah jam pulang'));

    actingAs($this->admin)
        ->get('/avana/absensi?date=2026-11-10')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.belum_absen', fn ($count): bool => $count >= 1)
            ->where('kpis.belum_absen_label', 'Belum Tercatat'));

    actingAs($this->admin)
        ->get('/avana/absensi?date=2026-11-20')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.belum_absen', null));
});

it('shows today\'s no-shows as rows of the rekap table', function (): void {
    $this->travelTo(Carbon::parse(ABSENCE_WEDNESDAY.' 10:00', TenantTime::zone($this->tenant->id)));

    $aliceRow = fn (array $rows): ?array => collect($rows)->firstWhere('id', -$this->alice->id);

    actingAs($this->admin)
        ->get('/avana/absensi?date='.ABSENCE_WEDNESDAY.'&per_page=500')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendances.data', fn ($rows): bool => ($aliceRow(collect($rows)->all())['status'] ?? null) === 'not_in')
            ->where('attendances.meta.total', fn ($total): bool => $total >= 1));

    // The status filter narrows the table to the no-shows alone.
    actingAs($this->admin)
        ->get('/avana/absensi?date='.ABSENCE_WEDNESDAY.'&status=not_in&per_page=500')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendances.data', fn ($rows): bool => collect($rows)->isNotEmpty()
                && collect($rows)->every(fn ($row): bool => $row['status'] === 'not_in')
                && $aliceRow(collect($rows)->all()) !== null));

    // Once Alice clocks in, she is a real row and no longer "belum absen".
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'date' => ABSENCE_WEDNESDAY,
        'status' => 'present',
        'clock_in_at' => ABSENCE_WEDNESDAY.' 08:00:00',
    ]);

    actingAs($this->admin)
        ->get('/avana/absensi?date='.ABSENCE_WEDNESDAY.'&per_page=500')
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendances.data', fn ($rows): bool => $aliceRow(collect($rows)->all()) === null
                && collect($rows)->contains(fn ($row): bool => ($row['employee']['id'] ?? null) === $this->alice->id && $row['status'] === 'present')));
});

it('lists a past day\'s unclosed no-shows as Belum Tercatat until they are marked', function (): void {
    $this->travelTo(Carbon::parse(ABSENCE_WEDNESDAY.' 10:00', TenantTime::zone($this->tenant->id)));
    $tuesday = '2026-11-10';
    clearAttendance($this->alice, $tuesday);

    $aliceRow = fn ($rows): ?array => collect($rows)->firstWhere('id', -$this->alice->id);

    actingAs($this->admin)
        ->get('/avana/absensi?date='.$tuesday.'&per_page=500')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.belum_absen_label', 'Belum Tercatat')
            ->where('attendances.data', fn ($rows): bool => ($aliceRow($rows)['status_label'] ?? null) === 'Belum Tercatat'));

    // Once the day is closed she is a real Alpa row instead.
    AbsenceMarker::markTenant($this->tenant->id, Carbon::parse($tuesday));

    actingAs($this->admin)
        ->get('/avana/absensi?date='.$tuesday.'&per_page=500')
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendances.data', fn ($rows): bool => $aliceRow($rows) === null
                && collect($rows)->contains(fn ($row): bool => ($row['employee']['id'] ?? null) === $this->alice->id && $row['status'] === 'absent')));
});

describe('laporan absensi', function (): void {
    beforeEach(function (): void {
        Route::middleware('web')->prefix('spec-absence')->group(function (): void {
            Route::get('laporan/export/{type}', [LaporanController::class, 'export']);
        });

        $this->travelTo(Carbon::parse('2026-11-13 10:00', TenantTime::zone($this->tenant->id)));
        clearAttendance($this->alice, '2026-11-10');
        clearAttendance($this->alice, '2026-11-12');
    });

    /** @return list<list<string>> */
    function exportRows(object $ctx, string $query): array
    {
        $csv = actingAs($ctx->admin)->get('spec-absence/laporan/export/absensi?'.$query)->assertOk()->streamedContent();

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
    }

    it('honours the Absensi page\'s date_from/date_to range', function (): void {
        $rows = exportRows($this, 'date_from=2026-11-12&date_to=2026-11-12');

        expect($rows[0][0])->toBe('Tanggal')
            ->and(collect(array_slice($rows, 1))->pluck(0)->unique()->values()->all())->toBe(['2026-11-12']);
    });

    it('lists Alpa rows and unclosed no-shows the way the page does', function (): void {
        AbsenceMarker::markTenant($this->tenant->id, Carbon::parse('2026-11-10'));

        $rows = collect(exportRows($this, 'date_from=2026-11-10&date_to=2026-11-12'))->slice(1);
        $alice = $rows->filter(fn (array $row): bool => $row[1] === $this->alice->full_name)->keyBy(0);

        // Closed by the marker: a real Alpa row.
        expect($alice['2026-11-10'][8])->toBe('Alpa')
            // Not closed yet: still listed, flagged as not recorded.
            ->and($alice['2026-11-12'][8])->toBe('Belum Tercatat')
            // Listed once per day, never twice.
            ->and($alice->count())->toBe($rows->filter(fn (array $row): bool => $row[1] === $this->alice->full_name)->count());
    });

    it('leaves exempt employees out of the no-show rows', function (): void {
        $this->alice->update(['attendance_exempt' => true]);

        $rows = collect(exportRows($this, 'date_from=2026-11-12&date_to=2026-11-12'))->slice(1);

        expect($rows->contains(fn (array $row): bool => $row[1] === $this->alice->full_name))->toBeFalse();
    });
});

describe('payroll', function (): void {
    beforeEach(function (): void {
        Route::middleware('web')->prefix('spec-absence')->group(function (): void {
            Route::post('payroll/run', [PayrollController::class, 'run']);
        });

        $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
        $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();
        $this->employee->update(['payday_id' => null]);

        $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
        giveMasterComponent($this->employee, $basic, 6_000_000);
        SalaryMaster::whereKey($this->employee->fresh()->salary_master_id)->update([
            'day_divisor' => 20,
            'day_calc_method_id' => null,
            'attendance_start_day' => null,
            'attendance_end_day' => null,
        ]);

        Attendance::where('employee_id', $this->employee->id)
            ->whereBetween('date', [$this->period->start_date->toDateString(), $this->period->end_date->toDateString()])
            ->delete();
    });

    function markAbsentDays(object $ctx, int $days): void
    {
        $date = $ctx->period->start_date->copy();

        for ($i = 0; $i < $days; $i++) {
            Attendance::create([
                'tenant_id' => $ctx->tenant->id,
                'employee_id' => $ctx->employee->id,
                'branch_id' => $ctx->employee->branch_id,
                'date' => $date->toDateString(),
                'status' => 'absent',
            ]);
            $date = $date->addDay();
        }
    }

    function runAbsencePayroll(object $ctx): PayrollRunItem
    {
        actingAs($ctx->admin)->post('spec-absence/payroll/run')->assertSessionHas('success');

        $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();

        return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();
    }

    it('docks the basic wage per Alpa day over the Master Gaji divisor', function (): void {
        markAbsentDays($this, 2);

        $item = runAbsencePayroll($this);
        $snapshot = $item->calculation_snapshot;
        $basicPaid = (float) collect($snapshot['earnings'])->firstWhere('name', 'Gaji Pokok')['amount'];
        $fullBasic = (float) $snapshot['absence']['daily_rate'] * 20;

        expect($snapshot['absence'])
            ->days->toBe(2)
            ->divisor->toBe(20)
            ->divisor_source->toBe('master_gaji')
            ->skipped->toBeNull()
            ->and($fullBasic)->toBeGreaterThanOrEqual($basicPaid);

        $line = collect($snapshot['deductions'])->firstWhere('name', 'Potongan Alpa (2 hari)');

        expect($line)->not->toBeNull()
            ->and((float) $line['amount'])->toBe(round($fullBasic / 20 * 2));
    });

    it('docks nothing when there was no Alpa', function (): void {
        $item = runAbsencePayroll($this);

        expect($item->calculation_snapshot['absence'])->toBeNull()
            ->and(collect($item->calculation_snapshot['deductions'])->pluck('name')->filter(
                fn (string $name): bool => str_starts_with($name, 'Potongan Alpa'),
            ))->toBeEmpty();
    });

    it('does not dock twice when the basic is already paid per present day', function (): void {
        PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->update(['calc_basis' => 'per_present_day']);
        markAbsentDays($this, 2);

        $item = runAbsencePayroll($this);

        expect($item->calculation_snapshot['absence'])
            ->skipped->toBe('gaji_pokok_mengikuti_kehadiran')
            ->amount->toEqual(0.0);
    });
});
