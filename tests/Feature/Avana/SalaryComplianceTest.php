<?php

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\SalaryGrade;
use App\Models\SalaryMaster;
use App\Models\Tenant;
use App\Models\UmrRate;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    $this->master = SalaryMaster::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MG-UMR-SPEC',
        'category' => 'Spec',
        'is_active' => true,
    ]);

    $this->employee->update(['salary_master_id' => $this->master->id]);

    $this->grade = SalaryGrade::create([
        'tenant_id' => $this->tenant->id,
        'grade_code' => 'G-SPEC',
        'grade_name' => 'Golongan Spec',
        'level' => 1,
        'min_salary' => 6_000_000,
        'mid_salary' => 8_000_000,
        'max_salary' => 10_000_000,
    ]);

    UmrRate::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'branch_id' => $this->employee->branch_id, 'year' => (int) now()->year],
        ['region' => 'Jakarta Pusat', 'amount' => 5_396_761],
    );
});

/** Set the employee's Gaji Pokok through the Master Gaji screen. */
function setComplianceBasic(object $ctx, float $amount, ?string $from = null): TestResponse
{
    return actingAs($ctx->admin)->post(
        route('avana.payroll.master-gaji.employee-salary', $ctx->master),
        [
            'employee_id' => $ctx->employee->id,
            'amount' => $amount,
            'effective_start_date' => $from,
        ],
    );
}

/** The validation row the setting screen renders for the employee. */
function complianceRow(object $ctx): array
{
    $props = actingAs($ctx->admin)
        ->get(route('avana.payroll.master-gaji.setting', $ctx->master))
        ->assertOk()
        ->viewData('page')['props'];

    return collect($props['salaries'])->firstWhere('id', $ctx->employee->id);
}

it('reports a salary above the branch UMR', function (): void {
    setComplianceBasic($this, 10_000_000)->assertSessionHas('success');

    $row = complianceRow($this);

    expect($row['basic'])->toBe(10_000_000.0);
    expect($row['umr_status'])->toBe('above');
    expect($row['umr_label'])->toBe('Di atas UMR');
    expect($row['umr_amount'])->toBe(5_396_761.0);
});

it('reports a salary below the branch UMR and warns on save', function (): void {
    setComplianceBasic($this, 4_650_000)
        ->assertSessionHas('warning')
        ->assertSessionMissing('success');

    $row = complianceRow($this);

    expect($row['umr_status'])->toBe('below');
    expect($row['umr_label'])->toBe('Di bawah UMR');
});

it('counts fixed allowances alongside the basic wage', function (): void {
    $allowance = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-JAB')->firstOrFail();
    $allowance->update(['calc_basis' => null, 'basis_type' => null]);

    $this->master->components()->updateOrCreate(
        ['payroll_component_id' => $allowance->id],
        ['included' => true, 'amount' => 2_375_000, 'is_prorate' => false, 'is_overtime_base' => true, 'is_kompensasi' => false],
    );

    setComplianceBasic($this, 4_000_000);

    $row = complianceRow($this);

    expect($row['allowances'])->toBe(2_375_000.0);
    expect($row['total'])->toBe(6_375_000.0);
    // 6.375.000 clears the 5.396.761 UMR even though the basic alone does not.
    expect($row['umr_status'])->toBe('above');
});

it('judges the salary against the grade band once a grade is assigned', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.master-gaji.employee-grade', $this->master), [
            'employee_id' => $this->employee->id,
            'salary_grade_id' => $this->grade->id,
        ])
        ->assertSessionHas('success');

    setComplianceBasic($this, 8_000_000);

    $row = complianceRow($this);

    expect($row['grade_status'])->toBe('within');
    expect($row['grade_min'])->toBe(6_000_000.0);
    expect($row['grade_max'])->toBe(10_000_000.0);
});

it('flags a salary above the grade band', function (): void {
    $this->employee->update(['salary_grade_id' => $this->grade->id]);

    setComplianceBasic($this, 12_000_000)->assertSessionHas('warning');

    expect(complianceRow($this)['grade_status'])->toBe('above_max');
});

it('says so plainly when no grade or UMR is on file', function (): void {
    UmrRate::forTenant($this->tenant->id)->delete();
    $this->employee->update(['salary_grade_id' => null]);

    setComplianceBasic($this, 8_000_000);

    $row = complianceRow($this);

    expect($row['umr_status'])->toBe('unknown');
    expect($row['umr_label'])->toBe('UMR belum diatur');
    expect($row['grade_status'])->toBe('unknown');
});

it('versions a salary change instead of overwriting the old figure', function (): void {
    setComplianceBasic($this, 8_000_000, '2026-01-01');
    setComplianceBasic($this, 10_000_000, '2026-07-01');

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    $rows = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->orderBy('effective_start_date')
        ->get();

    expect($rows)->toHaveCount(2);
    expect((float) $rows[0]->amount)->toBe(8_000_000.0);
    expect($rows[0]->effective_end_date->toDateString())->toBe('2026-06-30');
    expect((float) $rows[1]->amount)->toBe(10_000_000.0);
    expect($rows[1]->effective_end_date)->toBeNull();
});

it('corrects a same-day entry in place rather than stacking a version', function (): void {
    setComplianceBasic($this, 8_000_000, '2026-07-01');
    setComplianceBasic($this, 8_500_000, '2026-07-01');

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();

    $rows = EmployeeSalaryComponent::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('payroll_component_id', $basic->id)
        ->get();

    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]->amount)->toBe(8_500_000.0);
});

it('reads back the figure in force today', function (): void {
    setComplianceBasic($this, 8_000_000, '2026-01-01');
    setComplianceBasic($this, 11_000_000, now()->toDateString());

    expect(complianceRow($this)['basic'])->toBe(11_000_000.0);
    expect(complianceRow($this)['effective_from'])->toBe(now()->toDateString());
});

it('offers the grade list on the Master Gaji setting screen', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.master-gaji.setting', $this->master))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-master-gaji/setting', false)
            ->has('gradeOptions')
            ->has('salaries')
            ->etc());
});
