<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollFormula;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\UmrRate;
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

    Route::middleware('web')->prefix('spec-umr')->group(function (): void {
        Route::post('run', [PayrollController::class, 'run']);
    });
});

it('renders the UMR page and stores a rate', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.umr'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('avana/payroll-umr/index', false)->has('rates')->has('branchOptions'));

    actingAs($this->admin)
        ->post(route('avana.payroll.umr.store'), [
            'branch_id' => $this->employee->branch_id,
            'year' => (int) now()->year,
            'amount' => 5_100_000,
        ])
        ->assertSessionHas('success');

    $rate = UmrRate::forTenant($this->tenant->id)->where('branch_id', $this->employee->branch_id)->firstOrFail();
    expect((float) $rate->amount)->toBe(5_100_000.0);
});

it('resolves a formula UMR item to the branch UMR, preferring it over the default', function (): void {
    // Tenant-wide default + a branch-specific rate for the employee's branch.
    UmrRate::create(['tenant_id' => $this->tenant->id, 'branch_id' => null, 'year' => (int) now()->year, 'amount' => 4_000_000]);
    UmrRate::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->employee->branch_id, 'year' => (int) now()->year, 'amount' => 4_900_000]);

    // A component whose amount = UMR x 1 via a Master Formula umr item.
    $formula = PayrollFormula::create(['tenant_id' => $this->tenant->id, 'name' => 'UMR x1', 'is_active' => true]);
    $formula->items()->create(['tipe' => 'umr', 'payroll_component_id' => null, 'operator' => '*', 'nilai' => 1, 'prorate' => false, 'sort_order' => 1]);

    $component = PayrollComponent::create([
        'tenant_id' => $this->tenant->id, 'code' => 'UMR-BASE', 'name' => 'Gaji Sesuai UMR',
        'type' => 'earning', 'component_group' => 'penerimaan', 'is_taxable' => true, 'status' => 'active',
        'calc_basis' => 'fixed', 'basis_type' => 'formula', 'payroll_formula_id' => $formula->id,
    ]);
    giveMasterComponent($this->employee, $component, 0);

    actingAs($this->admin)->post('spec-umr/run')->assertSessionHas('success');
    $run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $this->employee->id)->firstOrFail();

    $earning = collect($item->calculation_snapshot['earnings'])->firstWhere('name', 'Gaji Sesuai UMR');
    expect((float) $earning['amount'])->toBe(4_900_000.0);
});
