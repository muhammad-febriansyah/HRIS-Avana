<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PayrollKomponenController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollComponentValue;
use App\Models\PayrollFormula;
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

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-dp')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('komponen/component', [PayrollKomponenController::class, 'storeComponent']);
        Route::post('komponen/component/{component}/nilai', [PayrollKomponenController::class, 'storeComponentValue']);
        Route::delete('komponen/nilai/{value}', [PayrollKomponenController::class, 'destroyComponentValue']);
    });
});

/** Create an earning component with a dasar perhitungan and attach it to the employee's position. */
function basisComponent(object $ctx, string $code, array $attributes, float $positionAmount = 0.0): PayrollComponent
{
    $component = PayrollComponent::create(array_merge([
        'tenant_id' => $ctx->tenant->id,
        'code' => $code,
        'name' => $code,
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'is_taxable' => true,
        'status' => 'active',
        'calc_basis' => 'fixed',
    ], $attributes));

    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $ctx->employee->position_id, 'payroll_component_id' => $component->id],
        ['tenant_id' => $ctx->tenant->id, 'amount' => $positionAmount],
    );

    return $component;
}

/** Run payroll and return the earning amount for a component name. */
function earningAmount(object $ctx, string $name): ?float
{
    actingAs($ctx->admin)->post('spec-dp/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();

    $row = collect($item->calculation_snapshot['earnings'])->firstWhere('name', $name);

    return $row !== null ? (float) $row['amount'] : null;
}

it('pays a fixed dasar perhitungan as its basis value', function (): void {
    basisComponent($this, 'DP-FIX', ['name' => 'Tunjangan Tetap', 'basis_type' => 'fixed', 'basis_value' => 500_000]);

    expect(earningAmount($this, 'Tunjangan Tetap'))->toBe(500_000.0);
});

it('resolves a tabel dasar perhitungan from the most-specific Nilai Komponen row', function (): void {
    $component = basisComponent($this, 'DP-TBL', ['name' => 'Tunjangan Tabel', 'basis_type' => 'tabel']);

    // Generic fallback (applies to anyone) and a position-specific override.
    PayrollComponentValue::create([
        'tenant_id' => $this->tenant->id,
        'payroll_component_id' => $component->id,
        'value' => 100_000,
    ]);
    PayrollComponentValue::create([
        'tenant_id' => $this->tenant->id,
        'payroll_component_id' => $component->id,
        'position_id' => $this->employee->position_id,
        'value' => 750_000,
    ]);

    expect(earningAmount($this, 'Tunjangan Tabel'))->toBe(750_000.0);
});

it('evaluates a formula dasar perhitungan from an operand component times its nilai', function (): void {
    $operand = basisComponent($this, 'DP-OP', ['name' => 'Basis Operand'], 4_000_000);

    $formula = PayrollFormula::create(['tenant_id' => $this->tenant->id, 'name' => 'Bonus 25%', 'is_active' => true]);
    $formula->items()->create([
        'tipe' => 'penerimaan',
        'payroll_component_id' => $operand->id,
        'operator' => '*',
        'nilai' => 0.25,
        'prorate' => false,
        'sort_order' => 1,
    ]);

    basisComponent($this, 'DP-FML', [
        'name' => 'Bonus Formula',
        'basis_type' => 'formula',
        'payroll_formula_id' => $formula->id,
    ]);

    expect(earningAmount($this, 'Bonus Formula'))->toBe(1_000_000.0);
});

it('clamps a formula dasar perhitungan to basis_max', function (): void {
    $operand = basisComponent($this, 'DP-OP2', ['name' => 'Basis Operand 2'], 4_000_000);

    $formula = PayrollFormula::create(['tenant_id' => $this->tenant->id, 'name' => 'Bonus Capped', 'is_active' => true]);
    $formula->items()->create([
        'tipe' => 'penerimaan',
        'payroll_component_id' => $operand->id,
        'operator' => '*',
        'nilai' => 0.5,
        'prorate' => false,
        'sort_order' => 1,
    ]);

    basisComponent($this, 'DP-FML2', [
        'name' => 'Bonus Dibatasi',
        'basis_type' => 'formula',
        'payroll_formula_id' => $formula->id,
        'basis_max' => 800_000,
    ]);

    // Raw formula = 4jt x 0.5 = 2jt, capped to 800rb.
    expect(earningAmount($this, 'Bonus Dibatasi'))->toBe(800_000.0);
});

it('stores and deletes a Nilai Komponen mapping row via the controller', function (): void {
    $component = basisComponent($this, 'DP-CRUD', ['name' => 'Tunjangan CRUD', 'basis_type' => 'tabel']);

    actingAs($this->admin)
        ->post('spec-dp/komponen/component/'.$component->id.'/nilai', [
            'position_id' => $this->employee->position_id,
            'value' => 325_000,
            'note' => 'Uji',
        ])
        ->assertSessionHas('success');

    $value = PayrollComponentValue::forTenant($this->tenant->id)
        ->where('payroll_component_id', $component->id)
        ->firstOrFail();

    expect((float) $value->value)->toBe(325_000.0);
    expect($value->position_id)->toBe($this->employee->position_id);

    actingAs($this->admin)
        ->delete('spec-dp/komponen/nilai/'.$value->id)
        ->assertSessionHas('success');

    expect(PayrollComponentValue::find($value->id))->toBeNull();
});

it('leaves legacy components (no basis_type) computing exactly as before', function (): void {
    // A plain fixed position amount with no basis_type keeps its nominal.
    basisComponent($this, 'DP-LEGACY', ['name' => 'Tunjangan Lama', 'basis_type' => null], 275_000);

    expect(earningAmount($this, 'Tunjangan Lama'))->toBe(275_000.0);
});
