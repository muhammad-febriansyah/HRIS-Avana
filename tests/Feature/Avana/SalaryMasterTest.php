<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\SalaryMasterController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use App\Models\SalaryMaster;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-mg')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('master-gaji', [SalaryMasterController::class, 'store']);
        Route::post('master-gaji/{master}/component', [SalaryMasterController::class, 'setComponent']);
        Route::post('master-gaji/{master}/assign', [SalaryMasterController::class, 'assign']);
    });
});

function fixedComponent(object $ctx, string $code, float $value): PayrollComponent
{
    return PayrollComponent::create([
        'tenant_id' => $ctx->tenant->id,
        'code' => $code,
        'name' => $code,
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'is_taxable' => true,
        'status' => 'active',
        'calc_basis' => 'fixed',
        'basis_type' => 'fixed',
        'basis_value' => $value,
    ]);
}

function mgEarnings(object $ctx): Collection
{
    actingAs($ctx->admin)->post('spec-mg/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();

    return collect($item->calculation_snapshot['earnings']);
}

it('pays a component checked into the employee assigned salary master', function (): void {
    $component = fixedComponent($this, 'MG-TJ', 300_000);

    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-1', 'category' => 'Organik', 'is_active' => true]);
    $master->components()->create(['payroll_component_id' => $component->id, 'is_prorate' => false]);

    $this->employee->update(['salary_master_id' => $master->id]);

    $earnings = mgEarnings($this);
    expect((float) $earnings->firstWhere('name', 'MG-TJ')['amount'])->toBe(300_000.0);
});

it('does not double-count a component present in both the master and the position', function (): void {
    $component = fixedComponent($this, 'MG-DUP', 250_000);

    // Also attach it to the position — the fixed basis yields 250k either way.
    PositionPayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'position_id' => $this->employee->position_id,
        'payroll_component_id' => $component->id,
        'amount' => 0,
    ]);

    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-2', 'category' => 'Organik', 'is_active' => true]);
    $master->components()->create(['payroll_component_id' => $component->id]);

    $this->employee->update(['salary_master_id' => $master->id]);

    $earnings = mgEarnings($this);
    expect($earnings->where('name', 'MG-DUP')->count())->toBe(1);
    expect((float) $earnings->firstWhere('name', 'MG-DUP')['amount'])->toBe(250_000.0);
});

it('creates a master, checks a component, and assigns an employee via the controller', function (): void {
    actingAs($this->admin)
        ->post('spec-mg/master-gaji', ['code' => 'MG-ORG', 'category' => 'Organik', 'day_divisor' => 22])
        ->assertSessionHas('success');

    $master = SalaryMaster::forTenant($this->tenant->id)->where('code', 'MG-ORG')->firstOrFail();
    expect($master->day_divisor)->toBe(22);

    $component = PayrollComponent::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', [
            'payroll_component_id' => $component->id,
            'checked' => true,
            'is_prorate' => true,
        ])
        ->assertSessionHas('success');

    expect($master->components()->count())->toBe(1);
    expect((bool) $master->components()->first()->is_prorate)->toBeTrue();

    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/assign', ['employee_ids' => [$this->employee->id]])
        ->assertSessionHas('success');

    expect($this->employee->fresh()->salary_master_id)->toBe($master->id);

    // Unchecking removes it from the checklist.
    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', [
            'payroll_component_id' => $component->id,
            'checked' => false,
        ])
        ->assertSessionHas('success');

    expect($master->components()->count())->toBe(0);
});

it('scopes a salary master to its tenant on the checklist endpoint', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active']);
    $master = SalaryMaster::create(['tenant_id' => $other->id, 'code' => 'MG-X', 'category' => 'Organik']);

    $component = PayrollComponent::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', ['payroll_component_id' => $component->id, 'checked' => true])
        ->assertNotFound();
});
