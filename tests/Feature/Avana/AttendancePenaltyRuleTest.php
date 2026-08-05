<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\AttendancePenalty;
use App\Models\AttendancePenaltyRule;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();
});

/** The client's own table: 10–30 menit Rp20.000, 30–60 menit Rp50.000. */
function seedLateTiers(int $tenantId): void
{
    AttendancePenaltyRule::create([
        'tenant_id' => $tenantId, 'violation_type' => 'late',
        'min_minutes' => 10, 'max_minutes' => 30,
        'penalty_type' => 'deduction', 'amount' => 20_000, 'is_active' => true,
    ]);

    AttendancePenaltyRule::create([
        'tenant_id' => $tenantId, 'violation_type' => 'late',
        'min_minutes' => 30, 'max_minutes' => 60,
        'penalty_type' => 'deduction', 'amount' => 50_000, 'is_active' => true,
    ]);
}

/** A late attendance row for the employee. */
function lateAttendance(int $tenantId, Employee $employee, string $date, int $minutes): Attendance
{
    return Attendance::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'date' => $date,
        'status' => 'late',
        'late_minutes' => $minutes,
    ]);
}

it('stores, edits and deletes a tenant penalty tier', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sanksi.aturan.store'), [
            'min_minutes' => 10,
            'max_minutes' => 30,
            'penalty_type' => 'deduction',
            'amount' => 20_000,
        ])
        ->assertSessionHasNoErrors();

    $rule = AttendancePenaltyRule::forTenant($this->tenant->id)->firstOrFail();

    expect((float) $rule->amount)->toBe(20000.0);

    actingAs($this->admin)
        ->post(route('avana.sanksi.aturan.store'), [
            'id' => $rule->id,
            'min_minutes' => 10,
            'max_minutes' => 30,
            'penalty_type' => 'deduction',
            'amount' => 25_000,
        ])
        ->assertSessionHasNoErrors();

    expect((float) $rule->fresh()->amount)->toBe(25000.0);

    actingAs($this->admin)
        ->delete(route('avana.sanksi.aturan.destroy', $rule))
        ->assertSessionHasNoErrors();

    expect(AttendancePenaltyRule::forTenant($this->tenant->id)->count())->toBe(0);
});

it('rejects a band that ends before it starts', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sanksi.aturan.store'), [
            'min_minutes' => 30,
            'max_minutes' => 10,
            'penalty_type' => 'deduction',
            'amount' => 20_000,
        ])
        ->assertSessionHasErrors('max_minutes');
});

it('fines each late arrival by the tier its minutes fall in', function (): void {
    seedLateTiers($this->tenant->id);

    // 8 menit = di dalam toleransi tabel (tidak ada tier yang cocok).
    lateAttendance($this->tenant->id, $this->employee, '2026-05-04', 8);
    lateAttendance($this->tenant->id, $this->employee, '2026-05-05', 25);
    lateAttendance($this->tenant->id, $this->employee, '2026-05-06', 45);
    lateAttendance($this->tenant->id, $this->employee, '2026-05-07', 90);

    actingAs($this->admin)
        ->post(route('avana.sanksi.generate'), [
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ])
        ->assertSessionHasNoErrors();

    $amounts = AttendancePenalty::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->orderBy('date')
        ->pluck('amount', 'date')
        ->map(fn ($amount): float => (float) $amount)
        ->all();

    expect($amounts['2026-05-04'])->toBe(0.0);
    expect($amounts['2026-05-05'])->toBe(20000.0);
    expect($amounts['2026-05-06'])->toBe(50000.0);
    // Past the last tier: no band covers 90 minutes, so no fine is invented.
    expect($amounts['2026-05-07'])->toBe(0.0);
});

it('deducts the fines from the payroll period that holds them', function (): void {
    Route::middleware('web')->prefix('spec-fine')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });

    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'locked']);

    $period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-05', 'name' => 'Mei 2026',
        'cycle' => 'monthly', 'start_date' => '2026-05-01', 'end_date' => '2026-05-31',
        'status' => 'draft',
    ]);

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);
    giveMasterComponent($this->employee, $basic, 10_000_000);

    AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-05-06',
        'violation_type' => 'late',
        'penalty_type' => 'deduction',
        'amount' => 50_000,
        'status' => 'active',
    ]);

    // A warning carries no money and must not touch take-home pay.
    AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-05-07',
        'violation_type' => 'late',
        'penalty_type' => 'warning',
        'amount' => 0,
        'status' => 'active',
    ]);

    actingAs($this->admin)->post('spec-fine/payroll/run')->assertSessionHas('success');

    $item = PayrollRunItem::where(
        'payroll_run_id',
        PayrollRun::where('payroll_period_id', $period->id)->latest('id')->value('id'),
    )->where('employee_id', $this->employee->id)->firstOrFail();

    $lines = collect($item->calculation_snapshot['deductions'] ?? []);
    $fine = $lines->firstWhere('name', 'Denda Absensi');

    expect($fine)->not->toBeNull();
    expect((float) $fine['amount'])->toBe(50000.0);
});

it('leaves a fine dated outside the period alone', function (): void {
    Route::middleware('web')->prefix('spec-fine-out')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });

    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'locked']);

    $period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-06', 'name' => 'Juni 2026',
        'cycle' => 'monthly', 'start_date' => '2026-06-01', 'end_date' => '2026-06-30',
        'status' => 'draft',
    ]);

    AttendancePenalty::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-05-06',
        'violation_type' => 'late',
        'penalty_type' => 'deduction',
        'amount' => 50_000,
        'status' => 'active',
    ]);

    actingAs($this->admin)->post('spec-fine-out/payroll/run')->assertSessionHas('success');

    $item = PayrollRunItem::where(
        'payroll_run_id',
        PayrollRun::where('payroll_period_id', $period->id)->latest('id')->value('id'),
    )->where('employee_id', $this->employee->id)->firstOrFail();

    $names = collect($item->calculation_snapshot['deductions'] ?? [])->pluck('name');

    expect($names)->not->toContain('Denda Absensi');
});
