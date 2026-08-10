<?php

use App\Http\Controllers\Avana\EmployeeSalaryController;
use App\Http\Controllers\Avana\SalaryAssignmentController;
use App\Http\Controllers\Avana\SalaryHistoryController;
use App\Http\Controllers\Avana\SalaryMasterController;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\SalaryMaster;
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
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();
    $this->master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-SETUP',
        'category' => 'Organik',
        'is_active' => true,
    ]);
    $this->employee->update(['salary_master_id' => $this->master->id]);

    Route::middleware('web')->prefix('spec-salary')->group(function (): void {
        Route::get('master-gaji/{master}/setting', [SalaryMasterController::class, 'setting'])->name('spec.salary.setting');
        Route::post('master-gaji/{master}/employee-salary', [SalaryMasterController::class, 'setEmployeeSalary']);
        Route::get('riwayat-gaji', [SalaryHistoryController::class, 'index'])->name('spec.salary.history');
        Route::post('riwayat-gaji/{version}/approve', [SalaryHistoryController::class, 'approve']);
        Route::post('riwayat-gaji/{version}/reject', [SalaryHistoryController::class, 'reject']);
        Route::get('gaji-karyawan', [EmployeeSalaryController::class, 'index'])->name('spec.salary.employee');
        Route::post('gaji-karyawan', [EmployeeSalaryController::class, 'store'])->name('spec.salary.employee.store');
        Route::get('penetapan-massal', [SalaryAssignmentController::class, 'index'])->name('spec.salary.mass');
        Route::post('penetapan-massal', [SalaryAssignmentController::class, 'apply'])->name('spec.salary.mass.apply');
    });
});

function lockPeriodThrough(object $ctx, string $endDate): void
{
    PayrollPeriod::create([
        'tenant_id' => $ctx->tenant->id,
        'code' => 'LOCKED-'.$endDate,
        'name' => 'Periode terkunci',
        'start_date' => date('Y-m-01', strtotime($endDate)),
        'end_date' => $endDate,
        'status' => 'locked',
    ]);
}

function postSalary(object $ctx, float $amount, ?string $from = null, ?string $reason = null)
{
    return actingAs($ctx->admin)->post('spec-salary/master-gaji/'.$ctx->master->id.'/employee-salary', [
        'employee_id' => $ctx->employee->id,
        'amount' => $amount,
        'effective_start_date' => $from,
        'reason' => $reason,
    ]);
}

it('refuses a salary that would start inside a finalized payroll period', function (): void {
    lockPeriodThrough($this, '2026-06-30');

    postSalary($this, 7_000_000, '2026-06-15')
        ->assertSessionHasErrors('effective_start_date');

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('effective_start_date', '2026-06-15')
        ->exists())->toBeFalse();
});

it('accepts a salary starting the day after the last finalized period', function (): void {
    lockPeriodThrough($this, '2026-06-30');

    postSalary($this, 7_000_000, '2026-07-01')->assertSessionHasNoErrors();

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->whereDate('effective_start_date', '2026-07-01')
        ->value('amount'))->toEqual(7_000_000);
});

it('leaves the salary date open when no payroll has been finalized', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    postSalary($this, 6_500_000, '2020-01-01')->assertSessionHasNoErrors();
});

it('keeps the reason, the author, the master and the contract on a salary version', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    $contract = EmployeeContract::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_number' => 'K-001',
        'contract_type' => 'pkwtt',
        'start_date' => '2026-01-01',
        'status' => 'active',
    ]);

    postSalary($this, 8_000_000, '2026-07-01', 'Promosi supervisor')->assertSessionHasNoErrors();

    $version = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('effective_start_date', '2026-07-01')
        ->firstOrFail();

    expect($version->reason)->toBe('Promosi supervisor');
    expect($version->created_by)->toBe($this->admin->id);
    expect($version->salary_master_id)->toBe($this->master->id);
    expect($version->employee_contract_id)->toBe($contract->id);
    expect($version->status)->toBe('active');
});

it('closes the previous version instead of overwriting it', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    postSalary($this, 6_000_000, '2026-07-01')->assertSessionHasNoErrors();
    postSalary($this, 7_000_000, '2026-09-01')->assertSessionHasNoErrors();

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    $versions = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->orderBy('effective_start_date')
        ->get();

    expect($versions)->toHaveCount(2);
    expect($versions[0]->effective_end_date->toDateString())->toBe('2026-08-31');
    expect($versions[1]->effective_end_date)->toBeNull();
});

it('tells the setting page the earliest date a salary change may start', function (): void {
    lockPeriodThrough($this, '2026-06-30');

    actingAs($this->admin)
        ->get('spec-salary/master-gaji/'.$this->master->id.'/setting')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-master-gaji/setting', false)
            ->where('salaryFloor', '2026-07-01'));
});

it('lists every salary version on the Riwayat Gaji screen', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    postSalary($this, 6_000_000, '2026-07-01', 'Penyesuaian awal')->assertSessionHasNoErrors();
    postSalary($this, 7_000_000, '2026-09-01', 'Kenaikan tahunan')->assertSessionHasNoErrors();

    actingAs($this->admin)
        ->get('spec-salary/riwayat-gaji?employee_id='.$this->employee->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-riwayat-gaji/index', false)
            ->where('employeeId', $this->employee->id)
            ->has('versions', 2)
            ->where('versions.0.reason', 'Kenaikan tahunan')
            ->where('versions.0.author', $this->admin->name)
            ->where('versions.1.effective_end_date', '2026-08-31'));
});

it('keeps salary history inside its own tenant', function (): void {
    $other = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-history', 'status' => 'active']);

    EmployeeSalaryComponent::create([
        'tenant_id' => $other->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => PayrollComponent::forTenant($this->tenant->id)->value('id'),
        'amount' => 9_000_000,
        'effective_start_date' => '2026-07-01',
    ]);

    actingAs($this->admin)
        ->get('spec-salary/riwayat-gaji')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'versions',
            fn ($versions) => collect($versions)->every(fn (array $v): bool => (float) $v['amount'] !== 9_000_000.0),
        ));
});

/** Give the master two included fixed components with template nominals. */
function seedMasterComponents(object $ctx): array
{
    $basic = PayrollComponent::forTenant($ctx->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed', 'basis_type' => null, 'status' => 'active']);

    $transport = PayrollComponent::updateOrCreate(
        ['tenant_id' => $ctx->tenant->id, 'code' => 'TJ-TRANSPORT'],
        [
            'name' => 'Tunjangan Transport',
            'type' => 'earning',
            'component_group' => 'penerimaan',
            'is_taxable' => true,
            'status' => 'active',
            'calc_basis' => 'fixed',
            'basis_type' => null,
        ],
    );

    $ctx->master->components()->updateOrCreate(['payroll_component_id' => $basic->id], ['included' => true, 'amount' => 5_000_000]);
    $ctx->master->components()->updateOrCreate(['payroll_component_id' => $transport->id], ['included' => true, 'amount' => 500_000]);

    return [$basic, $transport];
}

it('shows the master nominal for every component of the chosen employee', function (): void {
    [$basic, $transport] = seedMasterComponents($this);

    actingAs($this->admin)
        ->get('spec-salary/gaji-karyawan?employee_id='.$this->employee->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-gaji-karyawan/index', false)
            ->where('employee.id', $this->employee->id)
            ->has('rows', 2)
            ->where('rows', fn ($rows) => (float) collect($rows)
                ->firstWhere('id', $transport->id)['master_amount'] === 500_000.0));
});

it('saves a per-employee nominal for every fixed component at once', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    [$basic, $transport] = seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'reason' => 'Penyesuaian transport',
            'components' => [
                ['payroll_component_id' => $basic->id, 'amount' => 5_250_000],
                ['payroll_component_id' => $transport->id, 'amount' => 750_000],
            ],
        ])
        ->assertSessionHasNoErrors();

    $rows = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('effective_start_date', '2026-07-01')
        ->pluck('amount', 'payroll_component_id');

    expect((float) $rows[$basic->id])->toBe(5_250_000.0);
    expect((float) $rows[$transport->id])->toBe(750_000.0);
});

it('refuses the individual setup when the date sits in a finalized period', function (): void {
    lockPeriodThrough($this, '2026-06-30');

    [$basic] = seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-06-10',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 5_250_000]],
        ])
        ->assertSessionHasErrors('effective_start_date');

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('effective_start_date', '2026-06-10')
        ->exists())->toBeFalse();
});

it('previews the employees a filter selects before anything is applied', function (): void {
    seedMasterComponents($this);

    $mine = Employee::forTenant($this->tenant->id)->whereNotNull('branch_id')->orderBy('id')->firstOrFail();

    actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?salary_master_id='.$this->master->id.'&branch_id='.$mine->branch_id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-mass-assignment/index', false)
            ->where('template.component_count', 2)
            ->where('template.total', 5_500_000)
            ->where('preview', fn ($preview) => collect($preview)->isNotEmpty()
                && collect($preview)->every(fn (array $row): bool => $row['id'] !== null)));

    // Nothing is written by looking at the preview.
    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('salary_master_id', $this->master->id)
        ->exists())->toBeFalse();
});

it('nets a deduction out of the preview totals rather than adding it', function (): void {
    [$basic] = seedMasterComponents($this);

    $potongan = PayrollComponent::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'code' => 'POT-KOP'],
        [
            'name' => 'Potongan Koperasi',
            'type' => 'deduction',
            'component_group' => 'potongan',
            'status' => 'active',
            'calc_basis' => 'fixed',
            'basis_type' => null,
        ],
    );
    $this->master->components()->updateOrCreate(
        ['payroll_component_id' => $potongan->id],
        ['included' => true, 'amount' => 100_000],
    );

    actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?salary_master_id='.$this->master->id)
        ->assertOk()
        // 5.000.000 + 500.000 - 100.000, not the 5.600.000 a blind sum gives.
        ->assertInertia(fn ($page) => $page->where('template.total', 5_400_000));
});

it('copies the template nominals onto every employee it is applied to', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    [$basic, $transport] = seedMasterComponents($this);

    $others = Employee::forTenant($this->tenant->id)->orderBy('id')->take(3)->pluck('id');

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'employee_ids' => $others->all(),
            'effective_start_date' => '2026-07-01',
            'reason' => 'Penyesuaian UMR',
        ])
        ->assertSessionHas('success');

    foreach ($others as $employeeId) {
        expect(Employee::find($employeeId)->salary_master_id)->toBe($this->master->id);

        $rows = EmployeeSalaryComponent::forTenant($this->tenant->id)
            ->where('employee_id', $employeeId)
            ->whereDate('effective_start_date', '2026-07-01')
            ->pluck('amount', 'payroll_component_id');

        expect((float) $rows[$basic->id])->toBe(5_000_000.0);
        expect((float) $rows[$transport->id])->toBe(500_000.0);
    }
});

it('keeps an employee own figure unless the run is told to overwrite it', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    [$basic] = seedMasterComponents($this);

    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => $basic->id,
        'amount' => 9_000_000,
        'effective_start_date' => '2026-06-01',
    ]);

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'employee_ids' => [$this->employee->id],
            'effective_start_date' => '2026-07-01',
        ])
        ->assertSessionHas('success');

    expect((float) EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn('2026-07-01')
        ->value('amount'))->toBe(9_000_000.0);

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'employee_ids' => [$this->employee->id],
            'effective_start_date' => '2026-08-01',
            'existing' => 'overwrite',
        ])
        ->assertSessionHas('success');

    expect((float) EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn('2026-08-01')
        ->value('amount'))->toBe(5_000_000.0);
});

it('refuses a mass assignment dated inside a finalized period', function (): void {
    lockPeriodThrough($this, '2026-06-30');
    seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'employee_ids' => [$this->employee->id],
            'effective_start_date' => '2026-06-20',
        ])
        ->assertSessionHasErrors('effective_start_date');

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->whereDate('effective_start_date', '2026-06-20')
        ->exists())->toBeFalse();
});

it('freezes the template nominal on the employee so a later master edit does not re-price them', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    [$basic] = seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'employee_ids' => [$this->employee->id],
            'effective_start_date' => '2026-07-01',
        ])
        ->assertSessionHas('success');

    // The template is revised afterwards; the assigned employee keeps the
    // figure they were assigned.
    $this->master->components()->where('payroll_component_id', $basic->id)->update(['amount' => 12_000_000]);

    expect((float) EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn('2026-07-01')
        ->value('amount'))->toBe(5_000_000.0);
});

it('holds a salary change pending and pays nothing until it is approved', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    $this->tenant->update(['require_salary_approval' => true]);

    [$basic] = seedMasterComponents($this);

    // A figure already in force, so there is something the pending version
    // must not disturb.
    $current = EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => $basic->id,
        'amount' => 5_000_000,
        'status' => 'active',
        'effective_start_date' => '2026-06-01',
    ]);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'reason' => 'Kenaikan',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 7_000_000]],
        ])
        ->assertSessionHasNoErrors();

    $pending = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->whereDate('effective_start_date', '2026-07-01')
        ->firstOrFail();

    expect($pending->status)->toBe('pending_approval');
    // The running figure is untouched and still the one that pays.
    expect($current->fresh()->effective_end_date)->toBeNull();
    expect((float) EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn('2026-07-15')
        ->value('amount'))->toBe(5_000_000.0);

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/'.$pending->id.'/approve')
        ->assertSessionHas('success');

    expect($pending->fresh()->status)->toBe('active');
    expect($pending->fresh()->approved_by)->toBe($approver->id);
    expect($current->fresh()->effective_end_date->toDateString())->toBe('2026-06-30');
    expect((float) EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn('2026-07-15')
        ->value('amount'))->toBe(7_000_000.0);
});

it('refuses to let the author approve their own salary change', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    $this->tenant->update(['require_salary_approval' => true]);

    [$basic] = seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 7_000_000]],
        ])
        ->assertSessionHasNoErrors();

    $pending = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('status', 'pending_approval')
        ->firstOrFail();

    actingAs($this->admin)
        ->post('spec-salary/riwayat-gaji/'.$pending->id.'/approve')
        ->assertSessionHasErrors('status');

    expect($pending->fresh()->status)->toBe('pending_approval');
});

it('keeps a rejected salary version on the record without paying it', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    $this->tenant->update(['require_salary_approval' => true]);

    [$basic] = seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 7_000_000]],
        ])
        ->assertSessionHasNoErrors();

    $pending = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('status', 'pending_approval')
        ->firstOrFail();

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/'.$pending->id.'/reject')
        ->assertSessionHas('success');

    expect($pending->fresh()->status)->toBe('cancelled');
    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn('2026-07-15')
        ->exists())->toBeFalse();
});

it('replaces a same-day version on approval instead of leaving two in force', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    [$basic] = seedMasterComponents($this);

    // Today's figure is already active…
    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 6_000_000]],
        ])
        ->assertSessionHasNoErrors();

    // …and a second change lands on the same day, this time needing approval.
    $this->tenant->update(['require_salary_approval' => true]);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 6_500_000]],
        ])
        ->assertSessionHasNoErrors();

    $pending = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->where('status', 'pending_approval')
        ->firstOrFail();

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/'.$pending->id.'/approve')
        ->assertSessionHas('success');

    $inForce = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->inForce()
        ->effectiveOn()
        ->get();

    expect($inForce)->toHaveCount(1);
    expect((float) $inForce->first()->amount)->toBe(6_500_000.0);
});
