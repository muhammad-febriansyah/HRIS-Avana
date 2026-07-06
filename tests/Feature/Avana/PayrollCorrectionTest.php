<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\PayrollCorrection;
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
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-kor')->group(function (): void {
        Route::post('run', [PayrollController::class, 'run']);
    });
});

it('stores and approves a payroll correction', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.koreksi.store'), [
            'employee_id' => $this->employee->id,
            'correction_date' => $this->period->start_date->copy()->addDays(2)->toDateString(),
            'type' => 'earning',
            'amount' => 500_000,
            'reason' => 'Rapel tunjangan',
        ])
        ->assertSessionHas('success');

    $correction = PayrollCorrection::forTenant($this->tenant->id)->firstOrFail();
    expect($correction->status)->toBe('pending');

    actingAs($this->admin)
        ->post(route('avana.payroll.koreksi.approve', $correction))
        ->assertSessionHas('success');

    expect($correction->fresh()->status)->toBe('approved');
});

it('applies an approved correction to the payroll run within its period window', function (): void {
    $date = $this->period->start_date->copy()->addDays(3)->toDateString();

    PayrollCorrection::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'correction_date' => $date, 'type' => 'earning', 'amount' => 750_000,
        'reason' => 'Bonus proyek', 'status' => 'approved',
    ]);
    PayrollCorrection::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'correction_date' => $date, 'type' => 'deduction', 'amount' => 100_000,
        'reason' => 'Kelebihan bayar', 'status' => 'approved',
    ]);
    // A pending correction must NOT be applied.
    PayrollCorrection::create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
        'correction_date' => $date, 'type' => 'earning', 'amount' => 999_000,
        'reason' => 'Belum disetujui', 'status' => 'pending',
    ]);

    actingAs($this->admin)->post('spec-kor/run')->assertSessionHas('success');
    $run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $this->employee->id)->firstOrFail();

    $earnings = collect($item->calculation_snapshot['earnings']);
    $deductions = collect($item->calculation_snapshot['deductions']);

    expect((float) $earnings->firstWhere('name', 'Koreksi: Bonus proyek')['amount'])->toBe(750_000.0);
    expect((float) $deductions->firstWhere('name', 'Koreksi: Kelebihan bayar')['amount'])->toBe(100_000.0);
    expect($earnings->firstWhere('name', 'Koreksi: Belum disetujui'))->toBeNull();
});
