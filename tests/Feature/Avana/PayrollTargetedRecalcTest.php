<?php

use App\Http\Controllers\Avana\PayrollController;
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

/**
 * A thousand-employee payroll with four wrong figures should cost four
 * recomputations, not a thousand.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();

    Route::middleware('web')->prefix('spec-recalc')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('payroll/recalculate', [PayrollController::class, 'recalculate']);
    });

    actingAs($this->admin)->post('spec-recalc/payroll/run')->assertSessionHas('success');

    $this->run = PayrollRun::forTenant($this->tenant->id)
        ->where('payroll_period_id', $this->period->id)
        ->latest('id')
        ->firstOrFail();
});

/** The stored item for an employee in the current run. */
function runItem(object $ctx, int $employeeId): PayrollRunItem
{
    return PayrollRunItem::where('payroll_run_id', $ctx->run->id)
        ->where('employee_id', $employeeId)
        ->firstOrFail();
}

it('recomputes only the employees named and leaves the rest untouched', function (): void {
    $target = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();
    $other = Employee::forTenant($this->tenant->id)->where('id', '!=', $target->id)->orderBy('id')->firstOrFail();

    // Somebody else's row is edited by hand; a targeted rerun must not rewrite
    // it, which is what proves only the named rows were touched.
    runItem($this, $other->id)->update(['net_salary' => 1_234_567]);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    giveMasterComponent($target, $component, 7_500_000);

    actingAs($this->admin)
        ->post('spec-recalc/payroll/recalculate', ['employee_ids' => [$target->id]])
        ->assertSessionHas('success');

    expect((float) runItem($this, $target->id)->gross_salary)->toBe(7_500_000.0);
    expect((float) runItem($this, $other->id)->net_salary)->toBe(1_234_567.0);
});

it('re-adds the run header from the rows on file', function (): void {
    $target = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    giveMasterComponent($target, $component, 7_500_000);

    actingAs($this->admin)
        ->post('spec-recalc/payroll/recalculate', ['employee_ids' => [$target->id]])
        ->assertSessionHas('success');

    $run = $this->run->fresh();
    $sum = PayrollRunItem::where('payroll_run_id', $run->id)->sum('net_salary');

    expect((float) $run->total_net)->toBe((float) $sum)
        ->and($run->employee_count)->toBe(PayrollRunItem::where('payroll_run_id', $run->id)->count());
});

it('drops the approval, because the figures that were approved have moved', function (): void {
    $this->run->update(['status' => 'approved', 'approved_by' => $this->admin->id, 'approved_at' => now()]);

    $target = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    actingAs($this->admin)
        ->post('spec-recalc/payroll/recalculate', ['employee_ids' => [$target->id]])
        ->assertSessionHas('success');

    expect($this->run->fresh()->status)->toBe('calculated')
        ->and($this->run->fresh()->approved_by)->toBeNull();
});

it('refuses to touch a locked period', function (): void {
    $this->period->update(['status' => 'locked']);

    $target = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    actingAs($this->admin)
        ->post('spec-recalc/payroll/recalculate', ['employee_ids' => [$target->id]])
        ->assertSessionHasErrors('payroll');
});

it('refuses an employee who does not belong to the period', function (): void {
    $outsider = Employee::create([
        'tenant_id' => $this->tenant->id,
        'full_name' => 'Karyawan Baru',
        'employee_number' => 'NEW-001',
        'status' => 'active',
        'join_date' => $this->period->end_date->copy()->addMonth(),
    ]);

    actingAs($this->admin)
        ->post('spec-recalc/payroll/recalculate', ['employee_ids' => [$outsider->id]])
        ->assertSessionHasErrors('employee_ids');
});
