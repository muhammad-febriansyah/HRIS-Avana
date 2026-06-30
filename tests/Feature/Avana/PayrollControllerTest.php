<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PositionComponentController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Position;
use App\Models\PositionPayrollComponent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Self-contained routes for the payroll backend. The orchestrator wires the
    // production routes into routes/avana.php; these mirror the planned actions
    // on collision-free paths so the controllers can be exercised in isolation.
    Route::middleware('web')->prefix('spec-avana')->name('spec.')->group(function (): void {
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll');
        Route::post('payroll/periods', [PayrollController::class, 'storePeriod'])->name('payroll.periods.store');
        Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
        Route::get('payroll/components', [PositionComponentController::class, 'index'])->name('payroll.components');
        Route::put('payroll/components', [PositionComponentController::class, 'update'])->name('payroll.components.update');
    });
});

/**
 * Resolve the seeded earning/deduction component by code for the tenant.
 */
function payrollComponent(int $tenantId, string $code): PayrollComponent
{
    return PayrollComponent::forTenant($tenantId)->where('code', $code)->firstOrFail();
}

it('renders the payroll index with the expected props', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll/index', false)
            ->has('periods.data')
            ->has('periods.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('periode')
                ->has('bayar')
                ->has('karyawan')
                ->has('netR')
                ->has('grossR')
                ->has('status')
                ->has('status_label')
                ->etc())
            ->has('summary', fn (Assert $summary) => $summary
                ->has('period')
                ->has('status')
                ->has('status_label')
                ->has('total_gross')
                ->has('total_deduction')
                ->has('total_tax')
                ->has('total_net')
                ->has('employee_count'))
            ->has('slip', fn (Assert $slip) => $slip
                ->has('employee')
                ->has('earnings')
                ->has('deductions')
                ->has('gross')
                ->has('deduction')
                ->has('net'))
            ->has('filters'));
});

it('only lists payroll periods for the current tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    PayrollPeriod::create([
        'tenant_id' => $otherTenant->id,
        'code' => '2026-07',
        'name' => 'Juli 2026',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'pay_date' => '2026-07-25',
        'status' => 'draft',
    ]);

    actingAs($this->admin)
        ->get('spec-avana/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('periods.data', 1));
});

it('computes a payroll run with items for every active employee', function (): void {
    $tenantId = $this->tenant->id;
    $employee = Employee::forTenant($tenantId)->where('status', 'active')->orderBy('id')->firstOrFail();

    PositionPayrollComponent::create([
        'tenant_id' => $tenantId,
        'position_id' => $employee->position_id,
        'payroll_component_id' => payrollComponent($tenantId, 'TJ-JAB')->id,
        'amount' => 5_000_000,
    ]);
    EmployeeSalaryComponent::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'payroll_component_id' => payrollComponent($tenantId, 'BASIC')->id,
        'amount' => 2_000_000,
    ]);
    EmployeeSalaryComponent::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'payroll_component_id' => payrollComponent($tenantId, 'POT-KOP')->id,
        'amount' => 500_000,
    ]);

    $activeCount = Employee::forTenant($tenantId)->where('status', 'active')->count();

    actingAs($this->admin)
        ->post('spec-avana/payroll/run')
        ->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('status', 'draft')->orderByDesc('start_date')->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();

    expect($run->status)->toBe('calculated');
    expect((int) $run->employee_count)->toBe($activeCount);
    expect(PayrollRunItem::where('payroll_run_id', $run->id)->count())->toBe($activeCount);

    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();

    expect((float) $item->gross_salary)->toBe(7_000_000.0);
    expect((float) $item->total_allowance)->toBe(5_000_000.0);
    expect((float) $item->total_deduction)->toBe(500_000.0);
    expect((float) $item->net_salary)->toBe(6_500_000.0);
    expect($item->calculation_snapshot)->toHaveKey('earnings');
});

it('refreshes the existing run without duplicating items on re-run', function (): void {
    $tenantId = $this->tenant->id;
    $activeCount = Employee::forTenant($tenantId)->where('status', 'active')->count();

    actingAs($this->admin)->post('spec-avana/payroll/run')->assertSessionHas('success');
    actingAs($this->admin)->post('spec-avana/payroll/run')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('status', 'draft')->orderByDesc('start_date')->firstOrFail();
    $runs = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->get();

    expect($runs)->toHaveCount(1);
    expect(PayrollRunItem::where('payroll_run_id', $runs->first()->id)->count())->toBe($activeCount);
});

it('renders the position component matrix props', function (): void {
    $tenantId = $this->tenant->id;
    $position = Position::forTenant($tenantId)->orderBy('id')->firstOrFail();

    PositionPayrollComponent::create([
        'tenant_id' => $tenantId,
        'position_id' => $position->id,
        'payroll_component_id' => payrollComponent($tenantId, 'TJ-JAB')->id,
        'amount' => 1_250_000,
    ]);

    actingAs($this->admin)
        ->get('spec-avana/payroll/components')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-components/index', false)
            ->has('positions')
            ->has('components.0', fn (Assert $component) => $component
                ->has('id')
                ->has('name')
                ->has('type')
                ->has('calc_basis')
                ->etc())
            ->has('matrix.0', fn (Assert $row) => $row
                ->where('position_id', $position->id)
                ->has('payroll_component_id')
                ->has('amount')));
});

it('persists per-position component nominals on update', function (): void {
    $tenantId = $this->tenant->id;
    $position = Position::forTenant($tenantId)->orderBy('id')->firstOrFail();
    $component = payrollComponent($tenantId, 'TJ-JAB');

    actingAs($this->admin)
        ->put('spec-avana/payroll/components', [
            'items' => [
                ['position_id' => $position->id, 'payroll_component_id' => $component->id, 'amount' => 750_000],
            ],
        ])
        ->assertSessionHas('success');

    $row = PositionPayrollComponent::forTenant($tenantId)
        ->where('position_id', $position->id)
        ->where('payroll_component_id', $component->id)
        ->firstOrFail();

    expect((float) $row->amount)->toBe(750_000.0);
});

it('updates the nominal when the position component already exists', function (): void {
    $tenantId = $this->tenant->id;
    $position = Position::forTenant($tenantId)->orderBy('id')->firstOrFail();
    $component = payrollComponent($tenantId, 'TJ-JAB');

    PositionPayrollComponent::create([
        'tenant_id' => $tenantId,
        'position_id' => $position->id,
        'payroll_component_id' => $component->id,
        'amount' => 100_000,
    ]);

    actingAs($this->admin)
        ->put('spec-avana/payroll/components', [
            'items' => [
                ['position_id' => $position->id, 'payroll_component_id' => $component->id, 'amount' => 900_000],
            ],
        ])
        ->assertSessionHas('success');

    expect(PositionPayrollComponent::forTenant($tenantId)->count())->toBe(1);
    expect((float) PositionPayrollComponent::forTenant($tenantId)->firstOrFail()->amount)->toBe(900_000.0);
});

it('rejects per-position nominals that reference another tenant', function (): void {
    $tenantId = $this->tenant->id;
    $component = payrollComponent($tenantId, 'TJ-JAB');

    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreignPosition = Position::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'FOREIGN',
        'name' => 'Foreign Role',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put('spec-avana/payroll/components', [
            'items' => [
                ['position_id' => $foreignPosition->id, 'payroll_component_id' => $component->id, 'amount' => 500_000],
            ],
        ])
        ->assertSessionHasErrors('items.0.position_id');

    expect(PositionPayrollComponent::where('position_id', $foreignPosition->id)->count())->toBe(0);
});

it('forbids users without payroll permissions from viewing payroll', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get('spec-avana/payroll')
        ->assertForbidden();
});

it('creates a weekly payroll period', function (): void {
    actingAs($this->admin)
        ->post('spec-avana/payroll/periods', [
            'name' => 'Gaji Minggu 1 Juli 2026',
            'cycle' => 'weekly',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
            'pay_date' => '2026-07-13',
        ])
        ->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($this->tenant->id)->where('cycle', 'weekly')->firstOrFail();

    expect($period->cycle)->toBe('weekly');
    expect($period->status)->toBe('draft');
    expect($period->code)->toStartWith('WK-2026070');
    expect($period->start_date->toDateString())->toBe('2026-07-06');
    expect($period->end_date->toDateString())->toBe('2026-07-12');
});

it('rejects a period whose end date precedes the start date', function (): void {
    actingAs($this->admin)
        ->post('spec-avana/payroll/periods', [
            'name' => 'Salah',
            'cycle' => 'weekly',
            'start_date' => '2026-07-12',
            'end_date' => '2026-07-06',
        ])
        ->assertSessionHasErrors(['end_date']);
});

it('scopes present-day pay to the weekly period window', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    $component = PayrollComponent::forTenant($this->tenant->id)->where('calc_basis', 'per_present_day')->first()
        ?? PayrollComponent::forTenant($this->tenant->id)->firstOrFail();
    $component->update(['calc_basis' => 'per_present_day', 'type' => 'earning']);

    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $employee->position_id, 'payroll_component_id' => $component->id],
        ['tenant_id' => $this->tenant->id, 'amount' => 25000],
    );

    actingAs($this->admin)->post('spec-avana/payroll/periods', [
        'name' => 'Minggu Uji',
        'cycle' => 'weekly',
        'start_date' => '2026-07-06',
        'end_date' => '2026-07-12',
    ])->assertSessionHas('success');

    // 2 present days inside the window, 1 the following week (out of window).
    foreach (['2026-07-06', '2026-07-08', '2026-07-15'] as $date) {
        Attendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'date' => $date,
            'status' => 'present',
        ]);
    }

    actingAs($this->admin)->post('spec-avana/payroll/run')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($this->tenant->id)->where('cycle', 'weekly')->firstOrFail();
    $item = PayrollRunItem::where('payroll_period_id', $period->id)->where('employee_id', $employee->id)->firstOrFail();

    expect($item->calculation_snapshot['present_days'])->toBe(2);
});
