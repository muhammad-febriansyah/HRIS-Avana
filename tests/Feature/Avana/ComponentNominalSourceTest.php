<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentValue;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SalaryMasterAssignment;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * Master Gaji (and the employee salary copied from it) is the only place a
 * rupiah nominal lives. Master Komponen describes how a component is worked
 * out; it carries no figure to pay from.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-nominal')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/** Run payroll and return this employee's item. */
function nominalItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-nominal/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();
}

/** The rupiah paid for one component on this employee's payslip. */
function paidAmount(PayrollRunItem $item, string $name): float
{
    $row = collect($item->calculation_snapshot['earnings'])->firstWhere('name', $name);

    return (float) ($row['amount'] ?? 0);
}

it('pays the Master Gaji nominal, not the figure left on Master Komponen', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();
    // A leftover nominal from before Master Gaji owned the figure.
    $component->update(['calc_basis' => 'fixed', 'basis_type' => 'fixed', 'basis_value' => 900_000]);

    giveMasterComponent($this->employee, $component, 500_000);

    expect(paidAmount(nominalItem($this), 'Tunjangan Transport'))->toBe(500_000.0);
});

it('pays the employee nominal when it differs from the template', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();
    $component->update(['calc_basis' => 'fixed', 'basis_type' => 'fixed', 'basis_value' => 900_000]);

    $master = giveMasterComponent($this->employee, $component, 500_000);

    SalaryMasterAssignment::apply(
        (int) $this->tenant->id,
        $master,
        collect([$this->employee]),
        Carbon::parse($this->period->start_date),
        actorId: (int) $this->admin->id,
    );

    EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $component->id)
        ->update(['amount' => 650_000]);

    expect(paidAmount(nominalItem($this), 'Tunjangan Transport'))->toBe(650_000.0);
});

it('pays nothing for a component nobody assigned a nominal to', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();
    $component->update(['calc_basis' => 'fixed', 'basis_type' => 'fixed', 'basis_value' => 900_000]);

    // Included in the template, but with no figure of its own.
    giveMasterComponent($this->employee, $component, 0);

    expect(paidAmount(nominalItem($this), 'Tunjangan Transport'))->toBe(0.0);
});

it('freezes the per-day rate an employee was assigned when the template changes', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-MKN')->firstOrFail();
    $component->update(['calc_basis' => 'per_present_day', 'basis_type' => 'fixed', 'basis_value' => 100_000]);

    $master = giveMasterComponent($this->employee, $component, 25_000);

    SalaryMasterAssignment::apply(
        (int) $this->tenant->id,
        $master,
        collect([$this->employee]),
        Carbon::parse($this->period->start_date),
        actorId: (int) $this->admin->id,
    );

    // The template is raised afterwards; the assigned employee keeps 25.000.
    $master->components()->where('payroll_component_id', $component->id)->update(['amount' => 100_000]);

    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 10);

    $item = nominalItem($this);
    $presentDays = (int) $item->calculation_snapshot['present_days'];

    expect(paidAmount($item, 'Tunjangan Makan'))->toBe(25_000.0 * $presentDays);
});

it('keeps the percentage figure on the component, since it is not rupiah', function (): void {
    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    giveMasterComponent($this->employee, $basic, 10_000_000);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();
    $component->update([
        'calc_basis' => 'percentage',
        'basis_type' => 'fixed',
        'basis_value' => 10,
        'percentage_of_component_id' => $basic->id,
    ]);

    giveMasterComponent($this->employee, $component, 0);

    expect(paidAmount(nominalItem($this), 'Tunjangan Transport'))->toBe(1_000_000.0);
});

it('refuses to store a rupiah nominal on a Master Komponen component', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.payroll.komponen.component.update', $component), [
            'code' => $component->code,
            'name' => $component->name,
            'group' => 'penerimaan',
            'calc_basis' => 'fixed',
            'basis_type' => 'fixed',
            'basis_value' => 750_000,
        ])
        ->assertRedirect();

    expect($component->fresh()->basis_value)->toBeNull();
});

it('keeps a percent typed on a Persentase component', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.payroll.komponen.component.update', $component), [
            'code' => $component->code,
            'name' => $component->name,
            'group' => 'penerimaan',
            'calc_basis' => 'percentage',
            'basis_type' => 'fixed',
            'basis_value' => 12,
        ])
        ->assertRedirect();

    expect((float) $component->fresh()->basis_value)->toBe(12.0);
});

it('ignores a Nilai Komponen mapping row when paying a component', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();
    // 'tabel' used to mean "read the mapping table"; the mapping is no longer a
    // source of money, so only the template's nominal is paid.
    $component->update(['calc_basis' => 'fixed', 'basis_type' => 'tabel', 'basis_value' => null]);

    PayrollComponentValue::create([
        'tenant_id' => $this->tenant->id,
        'payroll_component_id' => $component->id,
        'value' => 900_000,
    ]);

    giveMasterComponent($this->employee, $component, 400_000);

    expect(paidAmount(nominalItem($this), 'Tunjangan Transport'))->toBe(400_000.0);
});

it('refuses to store a new Nilai Komponen nominal', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.payroll.komponen.nilai.store', $component), ['value' => 750_000])
        ->assertSessionHasErrors('value');

    expect(PayrollComponentValue::where('payroll_component_id', $component->id)->exists())->toBeFalse();
});
