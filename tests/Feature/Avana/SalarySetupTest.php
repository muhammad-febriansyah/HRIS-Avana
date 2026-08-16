<?php

use App\Http\Controllers\Avana\EmployeeSalaryController;
use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\SalaryAssignmentController;
use App\Http\Controllers\Avana\SalaryHistoryController;
use App\Http\Controllers\Avana\SalaryMasterController;
use App\Models\CustomField;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\SalaryChangeSet;
use App\Models\SalaryMaster;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmployeeSalaryWriter;
use App\Services\SalaryMasterAssignment;
use App\Support\SalaryPeriodLock;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
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
        Route::post('master-gaji/{master}/assign', [SalaryMasterController::class, 'assign']);
        Route::post('master-gaji/{master}/employee-salary', [SalaryMasterController::class, 'setEmployeeSalary']);
        Route::get('riwayat-gaji', [SalaryHistoryController::class, 'index'])->name('spec.salary.history');
        Route::post('riwayat-gaji/batch/approve', [SalaryHistoryController::class, 'approveBatch']);
        Route::post('riwayat-gaji/batch/reject', [SalaryHistoryController::class, 'rejectBatch']);
        Route::post('riwayat-gaji/{version}/approve', [SalaryHistoryController::class, 'approve']);
        Route::post('riwayat-gaji/{version}/reject', [SalaryHistoryController::class, 'reject']);
        Route::get('gaji-karyawan', [EmployeeSalaryController::class, 'index'])->name('spec.salary.employee');
        Route::post('gaji-karyawan', [EmployeeSalaryController::class, 'store'])->name('spec.salary.employee.store');
        Route::get('penetapan-massal', [SalaryAssignmentController::class, 'index'])->name('spec.salary.mass');
        Route::post('penetapan-massal', [SalaryAssignmentController::class, 'apply'])->name('spec.salary.mass.apply');
        Route::post('payroll-period', [PayrollController::class, 'storePeriod']);
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
        // A salary change without a reason is refused; tests about something
        // else carry a default one so they keep testing their own subject.
        'reason' => $reason ?? 'Penyesuaian gaji (spec)',
    ]);
}

function postMassAssignment(object $ctx, array $payload)
{
    $existing = $payload['existing'] ?? 'skip';
    $payload['reason'] = $payload['reason'] ?? 'Penetapan massal (spec)';
    $query = http_build_query([
        'salary_master_id' => $payload['salary_master_id'],
        'effective_start_date' => $payload['effective_start_date'],
        'existing' => $existing,
    ]);
    $props = actingAs($ctx->admin)
        ->get('spec-salary/penetapan-massal?'.$query)
        ->assertOk()
        ->viewData('page')['props'];

    return actingAs($ctx->admin)->post('spec-salary/penetapan-massal', [
        ...$payload,
        'existing' => $existing,
        'preview_employee_ids' => $props['previewEmployeeIds'],
        'preview_token' => $props['previewToken'],
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

it('bounds a retroactive salary version by its active successor', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    postSalary($this, 6_000_000, '2026-07-01')->assertSessionHasNoErrors();
    postSalary($this, 8_000_000, '2026-09-01')->assertSessionHasNoErrors();
    postSalary($this, 7_000_000, '2026-08-01')->assertSessionHasNoErrors();

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $versions = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->orderBy('effective_start_date')
        ->get();

    expect($versions)->toHaveCount(3)
        ->and($versions[0]->effective_end_date?->toDateString())->toBe('2026-07-31')
        ->and($versions[1]->effective_end_date?->toDateString())->toBe('2026-08-31')
        ->and($versions[2]->effective_end_date)->toBeNull();

    foreach (['2026-07-15', '2026-08-15', '2026-09-15'] as $date) {
        expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
            ->where('employee_id', $this->employee->id)
            ->where('payroll_component_id', $basic->id)
            ->inForce()
            ->effectiveOn($date)
            ->count())->toBe(1);
    }
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
            ->has('versions.data', 2)
            ->where('versions.meta.current_page', 1)
            ->where('versions.meta.total', 2)
            ->where('versions.data.0.reason', 'Kenaikan tahunan')
            ->where('versions.data.0.author', $this->admin->name)
            ->where('versions.data.1.effective_end_date', '2026-08-31'));
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
            'versions.data',
            fn ($versions) => collect($versions)->every(fn (array $v): bool => (float) $v['amount'] !== 9_000_000.0),
        ));
});

it('paginates salary history while keeping the selected employee filter', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    $effectiveDate = Carbon::parse('2024-01-01');

    foreach (range(1, 26) as $month) {
        postSalary(
            $this,
            5_000_000 + ($month * 10_000),
            $effectiveDate->copy()->addMonths($month - 1)->toDateString(),
            'Perubahan ke-'.$month,
        )->assertSessionHasNoErrors();
    }

    actingAs($this->admin)
        ->get('spec-salary/riwayat-gaji?employee_id='.$this->employee->id.'&page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employeeId', $this->employee->id)
            ->has('versions.data', 1)
            ->where('versions.meta.current_page', 2)
            ->where('versions.meta.last_page', 2)
            ->where('versions.meta.per_page', 25)
            ->where('versions.meta.total', 26)
            ->where('versions.meta.from', 26)
            ->where('versions.meta.to', 26)
            ->where('versions.data.0.reason', 'Perubahan ke-1'));
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
            ->component('avana/payroll-master-gaji/index', false)
            ->where('tab', 'karyawan')
            ->where('employee.id', $this->employee->id)
            ->has('rows', 2)
            ->where('rows', fn ($rows) => (float) collect($rows)
                ->firstWhere('id', $transport->id)['master_amount'] === 500_000.0));
});

it('loads a newly selected master before an employee has an assignment', function (): void {
    [, $transport] = seedMasterComponents($this);
    $this->employee->update(['salary_master_id' => null]);

    actingAs($this->admin)
        ->get('spec-salary/gaji-karyawan?employee_id='.$this->employee->id.'&salary_master_id='.$this->master->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-master-gaji/index', false)
            ->where('tab', 'karyawan')
            ->where('employee.salary_master_id', $this->master->id)
            ->has('rows', 2)
            ->where('rows', fn ($rows) => (float) collect($rows)
                ->firstWhere('id', $transport->id)['master_amount'] === 500_000.0));
});

it('does not carry an old master-only component into an individual master change', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    [$basic, $transport] = seedMasterComponents($this);

    postMassAssignment($this, [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id],
        'effective_start_date' => '2026-07-01',
    ])->assertSessionHas('success');

    $replacement = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-INDIVIDUAL-NEW',
        'category' => 'Organik',
        'is_active' => true,
    ]);
    $replacement->components()->create([
        'payroll_component_id' => $basic->id,
        'included' => true,
        'amount' => 6_500_000,
    ]);

    actingAs($this->admin)
        ->get('spec-salary/gaji-karyawan?employee_id='.$this->employee->id.'&salary_master_id='.$replacement->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $basic->id));

    actingAs($this->admin)->post('spec-salary/gaji-karyawan', [
        'employee_id' => $this->employee->id,
        'salary_master_id' => $replacement->id,
        'effective_start_date' => '2026-09-01',
        'reason' => 'Penyesuaian gaji (spec)',
        'components' => [
            ['payroll_component_id' => $basic->id, 'amount' => 6_500_000],
        ],
    ])->assertSessionHasNoErrors();

    $effective = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->inForce()
        ->effectiveOn('2026-09-15')
        ->pluck('amount', 'payroll_component_id');

    expect((float) $effective[$basic->id])->toBe(6_500_000.0)
        ->and($effective->has($transport->id))->toBeFalse();
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
            'reason' => 'Penyesuaian gaji (spec)',
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
            ->component('avana/payroll-master-gaji/index', false)
            ->where('tab', 'massal')
            ->where('template.component_count', 2)
            ->where('template.total', 5_500_000)
            ->where('preview', fn ($preview) => collect($preview)->isNotEmpty()
                && collect($preview)->every(fn (array $row): bool => $row['id'] !== null)));

    // Nothing is written by looking at the preview.
    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('salary_master_id', $this->master->id)
        ->exists())->toBeFalse();
});

it('falls back to a safe effective date for a malformed preview URL', function (): void {
    seedMasterComponents($this);

    actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?salary_master_id='.$this->master->id.'&effective_start_date=not-a-date')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.effective_start_date', SalaryPeriodLock::suggestedDate((int) $this->tenant->id)->toDateString()));
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

it('projects and preserves an employee override outside the target template', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    seedMasterComponents($this);

    $special = PayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TJ-SPECIAL',
        'name' => 'Tunjangan Khusus',
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'status' => 'active',
        'calc_basis' => 'fixed',
    ]);
    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => $special->id,
        'source_type' => 'employee_override',
        'amount' => 200_000,
        'status' => 'active',
        'effective_start_date' => '2026-06-01',
    ]);

    $props = actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?'.http_build_query([
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'existing' => 'skip',
        ]))
        ->assertOk()
        ->viewData('page')['props'];
    $row = collect($props['preview'])->firstWhere('id', $this->employee->id);

    expect((float) $row['template_total'])->toBe(5_700_000.0)
        ->and($row['has_own_figures'])->toBeTrue()
        ->and($row['override_count'])->toBe(1);

    postMassAssignment($this, [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id],
        'effective_start_date' => '2026-07-01',
        'existing' => 'skip',
    ])->assertSessionHas('success');

    expect((float) EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $special->id)
        ->inForce()
        ->effectiveOn('2026-07-01')
        ->value('amount'))->toBe(200_000.0);
});

it('rejects a stale mass preview without writing any salary rows', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    [$basic] = seedMasterComponents($this);
    $props = actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?'.http_build_query([
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'existing' => 'skip',
        ]))
        ->assertOk()
        ->viewData('page')['props'];

    $this->master->components()->where('payroll_component_id', $basic->id)->update(['amount' => 6_000_000]);

    actingAs($this->admin)->post('spec-salary/penetapan-massal', [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id],
        'preview_employee_ids' => $props['previewEmployeeIds'],
        'preview_token' => $props['previewToken'],
        'effective_start_date' => '2026-07-01',
        'existing' => 'skip',
    ])->assertSessionHasErrors('preview_token');

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->whereDate('effective_start_date', '2026-07-01')
        ->exists())->toBeFalse();
});

it('keeps the legacy master assignment endpoint read-only and redirects to preview', function (): void {
    seedMasterComponents($this);

    actingAs($this->admin)
        ->post('spec-salary/master-gaji/'.$this->master->id.'/assign', [
            'employee_ids' => [$this->employee->id],
        ])
        ->assertRedirect(route('avana.payroll.penetapan-massal', ['salary_master_id' => $this->master->id]));

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('salary_master_id', $this->master->id)
        ->exists())->toBeFalse();
});

it('rejects a salary date that splits an existing payroll period', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    PayrollPeriod::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'BOUNDARY-2030-01',
        'name' => 'Januari 2030',
        'start_date' => '2030-01-01',
        'end_date' => '2030-01-31',
        'status' => 'draft',
    ]);
    [$basic] = seedMasterComponents($this);

    actingAs($this->admin)->post('spec-salary/gaji-karyawan', [
        'employee_id' => $this->employee->id,
        'salary_master_id' => $this->master->id,
        'effective_start_date' => '2030-01-15',
        'reason' => 'Penyesuaian gaji (spec)',
        'components' => [['payroll_component_id' => $basic->id, 'amount' => 5_000_000]],
    ])->assertSessionHasErrors('effective_start_date');

    actingAs($this->admin)->post('spec-salary/gaji-karyawan', [
        'employee_id' => $this->employee->id,
        'salary_master_id' => $this->master->id,
        'effective_start_date' => '2030-01-01',
        'reason' => 'Penyesuaian gaji (spec)',
        'components' => [['payroll_component_id' => $basic->id, 'amount' => 5_000_000]],
    ])->assertSessionHasNoErrors();
});

it('rejects a new payroll period that would split a scheduled salary version', function (): void {
    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    EmployeeSalaryComponent::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'payroll_component_id' => $basic->id,
        'amount' => 6_000_000,
        'status' => 'active',
        'effective_start_date' => '2031-01-15',
    ]);

    actingAs($this->admin)->post('spec-salary/payroll-period', [
        'name' => 'Januari 2031',
        'cycle' => 'monthly',
        'start_date' => '2031-01-01',
        'end_date' => '2031-01-31',
    ])->assertSessionHasErrors('start_date');

    expect(PayrollPeriod::forTenant($this->tenant->id)
        ->where('name', 'Januari 2031')
        ->exists())->toBeFalse();
});

it('copies the template nominals onto every employee it is applied to', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);

    [$basic, $transport] = seedMasterComponents($this);

    $others = Employee::forTenant($this->tenant->id)->orderBy('id')->take(3)->pluck('id');

    postMassAssignment($this, [
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

    postMassAssignment($this, [
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

    postMassAssignment($this, [
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

    postMassAssignment($this, [
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

    postMassAssignment($this, [
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

it('replaces copied master values while preserving an employee override', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    [$basic, $transport] = seedMasterComponents($this);

    $legacyAllowance = PayrollComponent::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'TJ-LEGACY',
        'name' => 'Tunjangan Master Lama',
        'type' => 'earning',
        'component_group' => 'penerimaan',
        'status' => 'active',
        'calc_basis' => 'fixed',
    ]);
    $this->master->components()->create([
        'payroll_component_id' => $legacyAllowance->id,
        'included' => true,
        'amount' => 250_000,
    ]);

    postMassAssignment($this, [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id],
        'effective_start_date' => '2026-07-01',
    ])->assertSessionHas('success');

    EmployeeSalaryWriter::record(
        (int) $this->tenant->id,
        (int) $this->employee->id,
        (int) $transport->id,
        750_000,
        Carbon::parse('2026-08-01'),
    );

    $replacement = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-REPLACEMENT',
        'category' => 'Organik',
        'is_active' => true,
    ]);
    $replacement->components()->createMany([
        ['payroll_component_id' => $basic->id, 'included' => true, 'amount' => 6_000_000],
        ['payroll_component_id' => $transport->id, 'included' => true, 'amount' => 600_000],
    ]);

    postMassAssignment($this, [
        'salary_master_id' => $replacement->id,
        'employee_ids' => [$this->employee->id],
        'effective_start_date' => '2026-09-01',
        'existing' => 'skip',
    ])->assertSessionHas('success');

    $effective = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->inForce()
        ->effectiveOn('2026-09-15')
        ->pluck('amount', 'payroll_component_id');

    expect((float) $effective[$basic->id])->toBe(6_000_000.0)
        ->and((float) $effective[$transport->id])->toBe(750_000.0)
        ->and($effective->has($legacyAllowance->id))->toBeFalse();
});

it('resolves a future master only when its effective date is reached', function (): void {
    seedMasterComponents($this);
    $futureMaster = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-FUTURE',
        'category' => 'Organik',
        'is_active' => true,
    ]);
    $futureMaster->components()->create([
        'payroll_component_id' => PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->value('id'),
        'included' => true,
        'amount' => 8_000_000,
    ]);

    SalaryMasterAssignment::apply(
        (int) $this->tenant->id,
        $futureMaster,
        collect([$this->employee]),
        Carbon::parse('2026-09-01'),
        actorId: (int) $this->admin->id,
    );

    $employee = $this->employee->fresh();

    expect($employee->salary_master_id)->toBe($this->master->id)
        ->and(SalaryMasterAssignment::effectiveMasterId($employee, '2026-08-31'))->toBe($this->master->id)
        ->and(SalaryMasterAssignment::effectiveMasterId($employee, '2026-09-01'))->toBe($futureMaster->id);
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

it('approves every component in one salary change-set and defers the master link', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    $this->tenant->update(['require_salary_approval' => true]);
    [$basic, $transport] = seedMasterComponents($this);
    $this->employee->update(['salary_master_id' => null]);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'reason' => 'Penyesuaian gaji (spec)',
            'components' => [
                ['payroll_component_id' => $basic->id, 'amount' => 7_000_000],
                ['payroll_component_id' => $transport->id, 'amount' => 800_000],
            ],
        ])
        ->assertSessionHasNoErrors();

    $set = SalaryChangeSet::forTenant($this->tenant->id)->latest('id')->firstOrFail();
    expect($set->status)->toBe('pending_approval');
    expect($this->employee->fresh()->salary_master_id)->not->toBe($this->master->id);

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/'.$set->components()->firstOrFail()->id.'/approve')
        ->assertSessionHas('success');

    expect($set->fresh()->status)->toBe('active');
    expect($set->components()->where('status', 'active')->count())->toBe(2);
    expect($this->employee->fresh()->salary_master_id)->toBe($this->master->id);
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
            'reason' => 'Penyesuaian gaji (spec)',
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
            'reason' => 'Penyesuaian gaji (spec)',
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
            'reason' => 'Penyesuaian gaji (spec)',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 6_000_000]],
        ])
        ->assertSessionHasNoErrors();

    // …and a second change lands on the same day, this time needing approval.
    $this->tenant->update(['require_salary_approval' => true]);

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'reason' => 'Penyesuaian gaji (spec)',
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

it('filters the mass assignment preview by contract and Field Kustom', function (): void {
    $colleague = Employee::forTenant($this->tenant->id)
        ->where('id', '!=', $this->employee->id)
        ->orderBy('id')
        ->firstOrFail();

    // Client / Placement / NPO are tenant facts, kept as Field Kustom.
    CustomField::create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'employee',
        'key' => 'client',
        'label' => 'Client',
        'type' => 'select',
        'options' => ['PT Alfa', 'PT Beta'],
        'status' => 'active',
    ]);

    $this->employee->update(['custom_data' => ['client' => 'PT Alfa']]);
    $colleague->update(['custom_data' => ['client' => 'PT Beta']]);

    EmployeeContract::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'contract_number' => 'PKWT-FILTER-1',
        'contract_type' => 'pkwt',
        'start_date' => '2026-01-01',
        'end_date' => '2027-01-01',
        'status' => 'active',
    ]);

    $previewIds = function (array $query): array {
        return actingAs($this->admin)
            ->get('spec-salary/penetapan-massal?'.http_build_query([
                'salary_master_id' => $this->master->id,
                'effective_start_date' => '2026-07-01',
                ...$query,
            ]))
            ->assertOk()
            ->viewData('page')['props']['previewEmployeeIds'];
    };

    // The Field Kustom filter narrows to the one employee at that client.
    expect($previewIds(['custom' => ['client' => 'PT Alfa']]))->toBe([$this->employee->id]);
    expect($previewIds(['custom' => ['client' => 'PT Beta']]))->toBe([$colleague->id]);

    // Contract in force on the effective date, and its opposite.
    expect($previewIds(['contract' => 'active']))->toBe([$this->employee->id]);
    expect($previewIds(['contract' => 'none']))->not->toContain($this->employee->id);

    // An unknown key is ignored rather than queried blindly.
    expect($previewIds(['custom' => ['tidak_dikenal' => 'x']]))->toContain($this->employee->id);
});

it('still refuses a stale preview when a filter narrowed the list', function (): void {
    CustomField::create([
        'tenant_id' => $this->tenant->id,
        'entity' => 'employee',
        'key' => 'placement',
        'label' => 'Placement',
        'type' => 'select',
        'options' => ['Site A'],
        'status' => 'active',
    ]);
    $this->employee->update(['custom_data' => ['placement' => 'Site A']]);

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $this->master->components()->updateOrCreate(
        ['payroll_component_id' => $basic->id],
        ['included' => true, 'amount' => 5_000_000],
    );

    $props = actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?'.http_build_query([
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'custom' => ['placement' => 'Site A'],
        ]))
        ->assertOk()
        ->viewData('page')['props'];

    // The employee's salary changes behind the open preview.
    $this->employee->touch();

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'existing' => 'skip',
            'employee_ids' => $props['previewEmployeeIds'],
            'preview_employee_ids' => $props['previewEmployeeIds'],
            'preview_token' => str_repeat('0', 64),
        ])
        ->assertSessionHasErrors('preview_token');
});

it('refuses a salary change with no reason, and records the figure it replaces', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    [$basic] = seedMasterComponents($this);

    // Setting a nominal that differs from the template needs a reason.
    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 6_000_000]],
        ])
        ->assertSessionHasErrors('reason');

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->whereDate('effective_start_date', '2026-07-01')
        ->exists())->toBeFalse();

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
            'reason' => 'Promosi ke supervisor',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 6_000_000]],
        ])
        ->assertSessionHasNoErrors();

    // Changing a salary already in force needs a reason too, even when the new
    // figure matches the template exactly.
    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-09-01',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 5_000_000]],
        ])
        ->assertSessionHasErrors('reason');

    actingAs($this->admin)
        ->post('spec-salary/gaji-karyawan', [
            'employee_id' => $this->employee->id,
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-09-01',
            'reason' => 'Kembali ke nominal template',
            'components' => [['payroll_component_id' => $basic->id, 'amount' => 5_000_000]],
        ])
        ->assertSessionHasNoErrors();

    $latest = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->whereDate('effective_start_date', '2026-09-01')
        ->firstOrFail();

    // Author, approver slot, effective date, old and new figure all on the row.
    expect((float) $latest->amount)->toBe(5_000_000.0)
        ->and((float) $latest->previous_amount)->toBe(6_000_000.0)
        ->and($latest->reason)->toBe('Kembali ke nominal template')
        ->and($latest->created_by)->toBe($this->admin->id);
});

it('refuses a mass assignment with no reason when it changes a live salary', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    seedMasterComponents($this);

    // First assignment: nobody has a salary yet, so no reason is demanded.
    postMassAssignment($this, [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id],
        'effective_start_date' => '2026-07-01',
        'reason' => '',
    ])->assertSessionHas('success');

    $props = actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?'.http_build_query([
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-09-01',
        ]))
        ->assertOk()
        ->viewData('page')['props'];

    actingAs($this->admin)
        ->post('spec-salary/penetapan-massal', [
            'salary_master_id' => $this->master->id,
            'employee_ids' => [$this->employee->id],
            'effective_start_date' => '2026-09-01',
            'existing' => 'skip',
            'preview_employee_ids' => $props['previewEmployeeIds'],
            'preview_token' => $props['previewToken'],
        ])
        ->assertSessionHasErrors('reason');
});

it('approves a whole mass assignment run as one decision', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    $this->tenant->update(['require_salary_approval' => true]);

    seedMasterComponents($this);

    $colleague = Employee::forTenant($this->tenant->id)
        ->where('id', '!=', $this->employee->id)
        ->orderBy('id')
        ->firstOrFail();

    postMassAssignment($this, [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id, $colleague->id],
        'effective_start_date' => '2026-07-01',
        'reason' => 'Penyesuaian UMR 2026',
    ])->assertSessionHas('success');

    $props = actingAs($this->admin)
        ->get('spec-salary/riwayat-gaji')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['batches'])->toHaveCount(1);

    $batch = $props['batches'][0];

    expect($batch['employee_count'])->toBe(2)
        ->and($batch['master'])->toBe($this->master->code)
        // The preparer may not sign off their own run.
        ->and($batch['can_approve'])->toBeFalse();

    actingAs($this->admin)
        ->post('spec-salary/riwayat-gaji/batch/approve', ['batch_id' => $batch['batch_id']])
        ->assertSessionHasErrors('batch_id');

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/batch/approve', ['batch_id' => $batch['batch_id']])
        ->assertSessionHas('success');

    expect(SalaryChangeSet::forTenant($this->tenant->id)->where('batch_id', $batch['batch_id'])->where('status', 'pending_approval')->count())
        ->toBe(0)
        ->and(EmployeeSalaryComponent::forTenant($this->tenant->id)
            ->whereIn('employee_id', [$this->employee->id, $colleague->id])
            ->where('status', 'pending_approval')
            ->count())->toBe(0);
});

it('rejects a whole run with one reason, paying none of it', function (): void {
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'draft']);
    $this->tenant->update(['require_salary_approval' => true]);

    seedMasterComponents($this);

    postMassAssignment($this, [
        'salary_master_id' => $this->master->id,
        'employee_ids' => [$this->employee->id],
        'effective_start_date' => '2026-07-01',
        'reason' => 'Penyesuaian UMR 2026',
    ])->assertSessionHas('success');

    $batchId = SalaryChangeSet::forTenant($this->tenant->id)->whereNotNull('batch_id')->value('batch_id');

    $approver = User::where('tenant_id', $this->tenant->id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/batch/reject', ['batch_id' => $batchId])
        ->assertSessionHasErrors('reason');

    actingAs($approver)
        ->post('spec-salary/riwayat-gaji/batch/reject', [
            'batch_id' => $batchId,
            'reason' => 'Anggaran belum disetujui direksi',
        ])
        ->assertSessionHas('success');

    expect(EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->inForce()
        ->effectiveOn('2026-07-15')
        ->exists())->toBeFalse();
});

it('explains an inactive Master Gaji instead of returning 404', function (): void {
    $this->master->update(['is_active' => false]);

    actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?'.http_build_query([
            'salary_master_id' => $this->master->id,
            'effective_start_date' => '2026-07-01',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('masterNotice', fn (?string $notice): bool => $notice !== null && str_contains($notice, 'nonaktif'))
            ->where('template', null)
            ->where('previewToken', null)
            ->etc());

    // A template that truly does not exist is still a 404.
    actingAs($this->admin)
        ->get('spec-salary/penetapan-massal?salary_master_id=999999&effective_start_date=2026-07-01')
        ->assertNotFound();
});
