<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\SalaryMasterController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
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
        Route::get('master-gaji/{master}/setting', [SalaryMasterController::class, 'setting'])->name('spec.mg.setting');
        Route::put('master-gaji/{master}', [SalaryMasterController::class, 'update'])->name('spec.mg.update');
        Route::post('master-gaji/{master}/component', [SalaryMasterController::class, 'setComponent']);
        Route::post('master-gaji/{master}/assign', [SalaryMasterController::class, 'assign']);
    });
});

it('renders the setting page and saves the perhitungan fields', function (): void {
    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-SET', 'category' => 'Organik']);

    actingAs($this->admin)
        ->get('spec-mg/master-gaji/'.$master->id.'/setting')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-master-gaji/setting', false)
            ->has('master')
            ->has('components')
            ->has('employeeOptions'));

    actingAs($this->admin)
        ->put('spec-mg/master-gaji/'.$master->id, [
            'code' => 'MG-SET',
            'category' => 'Organik HO',
            'day_divisor' => 21,
            'day_calc_method' => 'hari_kerja',
            'overtime_calc_method' => 'reguler',
            'attendance_start_day' => 25,
            'attendance_end_day' => 24,
            'attendance_period' => 'bulan_lalu',
            'probation_months' => 3,
        ])
        ->assertSessionHas('success');

    $master->refresh();
    expect($master->category)->toBe('Organik HO');
    expect($master->day_divisor)->toBe(21);
    expect($master->day_calc_method)->toBe('hari_kerja');
    expect($master->attendance_period)->toBe('bulan_lalu');
    expect($master->probation_months)->toBe(3);
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

    // Check the Penerimaan/Potongan membership flag, then the prorate flag.
    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', [
            'payroll_component_id' => $component->id,
            'flag' => 'included',
            'checked' => true,
        ])
        ->assertSessionHas('success');
    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', [
            'payroll_component_id' => $component->id,
            'flag' => 'is_prorate',
            'checked' => true,
        ])
        ->assertSessionHas('success');

    expect($master->components()->count())->toBe(1);
    $row = $master->components()->first();
    expect((bool) $row->included)->toBeTrue();
    expect((bool) $row->is_prorate)->toBeTrue();

    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/assign', ['employee_ids' => [$this->employee->id]])
        ->assertSessionHas('success');

    expect($this->employee->fresh()->salary_master_id)->toBe($master->id);

    // Clearing every flag removes the row from the checklist entirely.
    foreach (['included', 'is_prorate'] as $flag) {
        actingAs($this->admin)
            ->post('spec-mg/master-gaji/'.$master->id.'/component', [
                'payroll_component_id' => $component->id,
                'flag' => $flag,
                'checked' => false,
            ])
            ->assertSessionHas('success');
    }

    expect($master->components()->count())->toBe(0);
});

it('counts attendance from the master periode window (bulan lalu)', function (): void {
    // Juni 2026 period; attendance window 25 s.d 24, shifted "bulan lalu" =>
    // 25 Apr .. 24 May. Present days inside that window are the ones counted.
    $master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MG-WIN', 'category' => 'Organik', 'is_active' => true,
        'attendance_start_day' => 25, 'attendance_end_day' => 24, 'attendance_period' => 'bulan_lalu',
    ]);
    $this->employee->update(['salary_master_id' => $master->id]);

    Attendance::where('employee_id', $this->employee->id)->delete();
    foreach (['2026-05-05', '2026-05-06', '2026-05-07', '2026-06-10', '2026-06-11'] as $date) {
        Attendance::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
            'branch_id' => $this->employee->branch_id, 'date' => $date, 'status' => 'present',
        ]);
    }

    actingAs($this->admin)->post('spec-mg/payroll/run')->assertSessionHas('success');
    $run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $this->employee->id)->firstOrFail();

    // Only the 3 May days fall inside 25 Apr .. 24 May.
    expect((int) $item->calculation_snapshot['present_days'])->toBe(3);
});

it('prorates a master component by the Jumlah Hari divisor', function (): void {
    $component = fixedComponent($this, 'MG-PRO', 1_000_000);

    $master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MG-DIV', 'category' => 'Organik', 'is_active' => true,
        'day_divisor' => 20, 'day_calc_method' => 'absen',
    ]);
    $master->components()->create(['payroll_component_id' => $component->id, 'included' => true, 'is_prorate' => true]);
    $this->employee->update(['salary_master_id' => $master->id]);

    // 10 present days in the Juni period window, divisor 20 => factor 0.5.
    Attendance::where('employee_id', $this->employee->id)->delete();
    for ($d = 1; $d <= 10; $d++) {
        Attendance::create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id,
            'branch_id' => $this->employee->branch_id, 'date' => sprintf('2026-06-%02d', $d + 1), 'status' => 'present',
        ]);
    }

    $earnings = mgEarnings($this);
    expect((float) $earnings->firstWhere('name', 'MG-PRO')['amount'])->toBe(500_000.0);
});

/** Give the employee a fixed BASIC + a flat per-hour overtime component + OT hours. */
function seedOvertimeSetup(object $ctx): void
{
    $basic = PayrollComponent::forTenant($ctx->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed', 'basis_type' => null]);
    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $ctx->employee->position_id, 'payroll_component_id' => $basic->id],
        ['tenant_id' => $ctx->tenant->id, 'amount' => 6_000_000],
    );

    $lembur = PayrollComponent::updateOrCreate(
        ['tenant_id' => $ctx->tenant->id, 'code' => 'LEMBUR'],
        ['name' => 'Uang Lembur', 'type' => 'earning', 'component_group' => 'penerimaan', 'is_taxable' => true, 'status' => 'active', 'calc_basis' => 'per_overtime_hour', 'basis_type' => null],
    );
    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $ctx->employee->position_id, 'payroll_component_id' => $lembur->id],
        ['tenant_id' => $ctx->tenant->id, 'amount' => 30_000],
    );

    OvertimeRequest::create([
        'tenant_id' => $ctx->tenant->id, 'employee_id' => $ctx->employee->id, 'branch_id' => $ctx->employee->branch_id,
        'date' => $ctx->period->start_date->copy()->addDay()->toDateString(), 'hours' => 5, 'status' => 'approved',
    ]);
}

it('pays statutory overtime and suppresses the flat component when the master is Reguler', function (): void {
    seedOvertimeSetup($this);
    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-REG', 'category' => 'Organik', 'overtime_calc_method' => 'reguler']);
    $this->employee->update(['salary_master_id' => $master->id]);

    $earnings = mgEarnings($this);
    expect($earnings->firstWhere('name', 'Lembur'))->not->toBeNull();     // statutory line present
    expect($earnings->firstWhere('name', 'Uang Lembur'))->toBeNull();     // flat component suppressed
});

it('keeps the flat overtime component and no statutory line when the master is Flat', function (): void {
    seedOvertimeSetup($this);
    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-FLAT', 'category' => 'Organik', 'overtime_calc_method' => 'flat']);
    $this->employee->update(['salary_master_id' => $master->id]);

    $earnings = mgEarnings($this);
    expect((float) $earnings->firstWhere('name', 'Uang Lembur')['amount'])->toBe(150_000.0); // 30k x 5h flat
    expect($earnings->firstWhere('name', 'Lembur'))->toBeNull();          // no statutory double
});

it('does not mark a component included when only a section flag is toggled', function (): void {
    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-FLAG', 'category' => 'Organik']);
    $component = PayrollComponent::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', [
            'payroll_component_id' => $component->id,
            'flag' => 'is_prorate',
            'checked' => true,
        ])
        ->assertSessionHas('success');

    $row = $master->components()->where('payroll_component_id', $component->id)->firstOrFail();
    expect((bool) $row->is_prorate)->toBeTrue();
    expect((bool) $row->included)->toBeFalse();
});

it('scopes a salary master to its tenant on the checklist endpoint', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain', 'status' => 'active']);
    $master = SalaryMaster::create(['tenant_id' => $other->id, 'code' => 'MG-X', 'category' => 'Organik']);

    $component = PayrollComponent::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-mg/master-gaji/'.$master->id.'/component', ['payroll_component_id' => $component->id, 'checked' => true])
        ->assertNotFound();
});
