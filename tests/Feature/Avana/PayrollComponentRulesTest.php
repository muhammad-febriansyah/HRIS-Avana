<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryMaster;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    $this->master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-COMP-SPEC',
        'category' => 'Spec',
        'is_active' => true,
    ]);
    $this->employee->update(['salary_master_id' => $this->master->id]);

    // The BPJS premium must come off the components, so the employee carries no
    // separately reported wage.
    EmployeeBpjsProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id],
        [
            'registered_wage' => 0,
            'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
            'jp_enabled' => true, 'kesehatan_enabled' => true,
            'effective_start_date' => '2026-01-01',
        ],
    );

    Route::middleware('web')->prefix('spec-komponen')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/** Put a flat monthly component on the master with the given flags. */
function specComponent(object $ctx, string $code, float $amount, array $flags = []): PayrollComponent
{
    $component = PayrollComponent::forTenant($ctx->tenant->id)->where('code', $code)->firstOrFail();
    $component->update(['calc_basis' => null, 'basis_type' => null] + $flags);

    giveMasterComponent($ctx->employee, $component, $amount);

    EmployeeSalaryComponent::where('employee_id', $ctx->employee->id)
        ->where('payroll_component_id', $component->id)
        ->delete();

    return $component;
}

/** Run payroll and return this employee's item. */
function specRunItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-komponen/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)
        ->where('payroll_period_id', $ctx->period->id)
        ->latest('id')
        ->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)
        ->where('employee_id', $ctx->employee->id)
        ->firstOrFail();
}

it('computes the BPJS contribution from the components flagged as its base', function (): void {
    specComponent($this, 'BASIC', 8_000_000, ['is_bpjs_base' => true]);
    specComponent($this, 'TJ-JAB', 2_000_000, ['is_bpjs_base' => true]);
    // Deliberately outside the base.
    specComponent($this, 'TJ-MKN', 1_000_000, ['is_bpjs_base' => false]);

    $item = specRunItem($this);

    expect((float) $item->calculation_snapshot['bpjs']['base_wage'])->toBe(10_000_000.0);
});

it('falls back to the basic wage when no component is flagged', function (): void {
    specComponent($this, 'BASIC', 8_000_000, ['is_bpjs_base' => false]);
    specComponent($this, 'TJ-JAB', 2_000_000, ['is_bpjs_base' => false]);
    specComponent($this, 'TJ-MKN', 0, ['is_bpjs_base' => false]);

    $item = specRunItem($this);

    expect((float) $item->calculation_snapshot['bpjs']['base_wage'])->toBe(8_000_000.0);
});

it('still lets a separately reported wage override the component base', function (): void {
    specComponent($this, 'BASIC', 8_000_000, ['is_bpjs_base' => true]);
    specComponent($this, 'TJ-JAB', 2_000_000, ['is_bpjs_base' => true]);

    EmployeeBpjsProfile::where('employee_id', $this->employee->id)->update(['registered_wage' => 5_000_000]);

    $item = specRunItem($this);

    expect((float) $item->calculation_snapshot['bpjs']['base_wage'])->toBe(5_000_000.0);
});

it('stops paying a component once it is deactivated', function (): void {
    specComponent($this, 'BASIC', 8_000_000, ['is_bpjs_base' => true]);
    $allowance = specComponent($this, 'TJ-JAB', 2_000_000);

    $before = collect(specRunItem($this)->calculation_snapshot['earnings'])->pluck('name');
    expect($before)->toContain('Tunjangan Jabatan');

    $allowance->update(['status' => 'inactive']);

    $after = collect(specRunItem($this)->calculation_snapshot['earnings'])->pluck('name');
    expect($after)->not->toContain('Tunjangan Jabatan');
});

it('keeps a deactivated component out of the Master Gaji checklist', function (): void {
    PayrollComponent::forTenant($this->tenant->id)
        ->where('code', 'TJ-JAB')
        ->update(['status' => 'inactive']);

    actingAs($this->admin)
        ->get(route('avana.payroll.master-gaji.setting', $this->master))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $names = collect($page->toArray()['props']['components'])->pluck('name');

            expect($names)->not->toContain('Tunjangan Jabatan');
            expect($names)->toContain('Gaji Pokok');
        });
});

it('offers the BPJS base switch on the Master Komponen screen', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.komponen'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $basic = collect($page->toArray()['props']['components'])->firstWhere('code', 'BASIC');

            expect($basic)->toHaveKey('is_bpjs_base');
            expect($basic['is_bpjs_base'])->toBeTrue();
        });
});
