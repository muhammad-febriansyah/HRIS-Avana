<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);
    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $this->employee->position_id, 'payroll_component_id' => $basic->id],
        ['tenant_id' => $this->tenant->id, 'amount' => 7_000_000],
    );

    Route::middleware('web')->prefix('spec-export')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::get('payroll/payslip/{item}/pdf', [PayrollController::class, 'payslipPdf']);
        Route::get('payroll/transfer', [PayrollController::class, 'transferFile']);
        Route::get('payroll/bpjs', [PayrollController::class, 'bpjsFile']);
        Route::get('payroll/1721/{employee}', [PayrollController::class, 'taxForm1721']);
    });

    actingAs($this->admin)->post('spec-export/payroll/run')->assertSessionHas('success');
    $this->run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();
});

it('streams a password-protected payslip PDF', function (): void {
    $item = PayrollRunItem::where('payroll_run_id', $this->run->id)->where('employee_id', $this->employee->id)->firstOrFail();

    $response = actingAs($this->admin)->get("spec-export/payroll/payslip/{$item->id}/pdf");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

it('exports a BCA-format bank transfer file', function (): void {
    $response = actingAs($this->admin)->get('spec-export/payroll/transfer?bank=bca');

    $response->assertOk();
    expect($response->streamedContent())->toContain('No Rekening Tujuan');
});

it('falls back to the generic bank format for an unknown bank', function (): void {
    $response = actingAs($this->admin)->get('spec-export/payroll/transfer?bank=nonsense');

    $response->assertOk();
    expect($response->streamedContent())->toContain('Atas Nama');
});

it('exports the BPJS contribution report', function (): void {
    $response = actingAs($this->admin)->get('spec-export/payroll/bpjs');

    $response->assertOk();
    expect($response->streamedContent())->toContain('JHT (Karyawan)');
});

it('generates an annual 1721-A1 tax slip PDF', function (): void {
    $year = $this->period->start_date->year;

    $response = actingAs($this->admin)->get("spec-export/payroll/1721/{$this->employee->id}?year={$year}");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});
