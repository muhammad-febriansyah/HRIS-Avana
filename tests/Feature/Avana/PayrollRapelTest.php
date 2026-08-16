<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\SalaryRapelController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryRapel;
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
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-rapel')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('rapel', [SalaryRapelController::class, 'store']);
        Route::post('rapel/{rapel}/approve', [SalaryRapelController::class, 'approve']);
    });
});

/** Run payroll and return the employee's earnings/deductions collections. */
function rapelRun(object $ctx): array
{
    actingAs($ctx->admin)->post('spec-rapel/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();

    return [
        collect($item->calculation_snapshot['earnings']),
        collect($item->calculation_snapshot['deductions']),
    ];
}

it('back-pays the monthly difference for every whole elapsed month', function (): void {
    $periodStart = $this->period->start_date->copy();

    SalaryRapel::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'label' => 'Gaji Pokok',
        'old_amount' => 5_000_000,
        'new_amount' => 6_000_000,
        'effective_from' => $periodStart->copy()->subMonths(2)->startOfMonth()->toDateString(),
        'posting_date' => $periodStart->toDateString(),
        'reason' => 'SK kenaikan gaji',
        'status' => 'approved',
    ]);

    [$earnings] = rapelRun($this);
    $line = $earnings->firstWhere('name', 'Rapel: Gaji Pokok');

    expect($line)->not->toBeNull();
    // (6.000.000 − 5.000.000) × 2 bulan = 2.000.000
    expect((float) $line['amount'])->toBe(2_000_000.0);
});

it('ignores a rapel that has not been approved', function (): void {
    SalaryRapel::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'label' => 'Gaji Pokok',
        'old_amount' => 5_000_000,
        'new_amount' => 6_000_000,
        'effective_from' => $this->period->start_date->copy()->subMonths(2)->toDateString(),
        'posting_date' => $this->period->start_date->toDateString(),
        'reason' => 'Belum disetujui',
        'status' => 'pending',
    ]);

    [$earnings] = rapelRun($this);

    expect($earnings->firstWhere('name', 'Rapel: Gaji Pokok'))->toBeNull();
});

it('posts a negative difference as a deduction', function (): void {
    SalaryRapel::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'label' => 'Kelebihan Bayar',
        'old_amount' => 6_000_000,
        'new_amount' => 5_500_000,
        'effective_from' => $this->period->start_date->copy()->subMonths(3)->startOfMonth()->toDateString(),
        'posting_date' => $this->period->start_date->toDateString(),
        'reason' => 'Koreksi kelebihan',
        'status' => 'approved',
    ]);

    [, $deductions] = rapelRun($this);
    $line = $deductions->firstWhere('name', 'Rapel: Kelebihan Bayar');

    expect($line)->not->toBeNull();
    // |5.500.000 − 6.000.000| × 3 = 1.500.000
    expect((float) $line['amount'])->toBe(1_500_000.0);
});

it('does not back-pay when the effective month is not before the period', function (): void {
    SalaryRapel::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'label' => 'Gaji Pokok',
        'old_amount' => 5_000_000,
        'new_amount' => 6_000_000,
        'effective_from' => $this->period->start_date->toDateString(),
        'posting_date' => $this->period->start_date->toDateString(),
        'reason' => 'Efektif bulan berjalan',
        'status' => 'approved',
    ]);

    [$earnings] = rapelRun($this);

    expect($earnings->firstWhere('name', 'Rapel: Gaji Pokok'))->toBeNull();
});

it('stores and approves a rapel through the controller', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    actingAs($this->admin)
        ->post('spec-rapel/rapel', [
            'employee_id' => $this->employee->id,
            'payroll_component_id' => $component->id,
            'old_amount' => 5_000_000,
            'new_amount' => 5_800_000,
            'effective_from' => '2026-04-01',
            'posting_date' => '2026-06-01',
            'reason' => 'SK kenaikan',
        ])
        ->assertSessionHas('success');

    $rapel = SalaryRapel::forTenant($this->tenant->id)->latest('id')->firstOrFail();
    expect($rapel->status)->toBe('pending');

    // The maker cannot be the checker: approval comes from somebody else.
    actingAs($this->admin)
        ->post('spec-rapel/rapel/'.$rapel->id.'/approve')
        ->assertSessionHasErrors('status');

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-rapel/rapel/'.$rapel->id.'/approve')
        ->assertSessionHas('success');

    expect($rapel->fresh()->status)->toBe('approved');
    expect((int) $rapel->fresh()->approved_by)->toBe($approver->id);
});

it('rejects a posting date before the effective date', function (): void {
    actingAs($this->admin)
        ->post('spec-rapel/rapel', [
            'employee_id' => $this->employee->id,
            'old_amount' => 5_000_000,
            'new_amount' => 6_000_000,
            'effective_from' => '2026-06-01',
            'posting_date' => '2026-05-01',
            'reason' => 'Tanggal terbalik',
        ])
        ->assertSessionHasErrors('posting_date');
});
