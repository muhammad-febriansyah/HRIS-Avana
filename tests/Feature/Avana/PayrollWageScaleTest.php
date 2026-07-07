<?php

use App\Models\Employee;
use App\Models\Payday;
use App\Models\SalaryGrade;
use App\Models\SalaryGradeStep;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->grade = SalaryGrade::create([
        'tenant_id' => $this->tenant->id, 'grade_code' => 'G1', 'grade_name' => 'Golongan I',
        'level' => 1, 'min_salary' => 4_500_000, 'mid_salary' => 5_250_000, 'max_salary' => 6_000_000,
    ]);
});

it('renders Nilai Upah and stores a wage step, upserting on the same masa kerja', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.nilai-upah'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('avana/payroll-nilai-upah/index', false)->has('grades')->has('steps'));

    actingAs($this->admin)
        ->post(route('avana.payroll.nilai-upah.store'), [
            'salary_grade_id' => $this->grade->id, 'masa_kerja' => 0, 'amount' => 4_500_000,
        ])
        ->assertSessionHas('success');

    // Same grade + masa kerja updates in place rather than duplicating.
    actingAs($this->admin)
        ->post(route('avana.payroll.nilai-upah.store'), [
            'salary_grade_id' => $this->grade->id, 'masa_kerja' => 0, 'amount' => 4_800_000,
        ])
        ->assertSessionHas('success');

    $steps = SalaryGradeStep::forTenant($this->tenant->id)->where('salary_grade_id', $this->grade->id)->get();
    expect($steps)->toHaveCount(1);
    expect((float) $steps->first()->amount)->toBe(4_800_000.0);
});

it('deletes a wage step', function (): void {
    $step = SalaryGradeStep::create([
        'tenant_id' => $this->tenant->id, 'salary_grade_id' => $this->grade->id, 'masa_kerja' => 2, 'amount' => 5_000_000,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.payroll.nilai-upah.destroy', $step))
        ->assertSessionHas('success');

    expect(SalaryGradeStep::find($step->id))->toBeNull();
});

it('renders Mapping Payday, stores a payday and maps employees to it', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.payday'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('avana/payroll-payday/index', false)->has('paydays')->has('employees'));

    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), [
            'code' => 'PD-STAFF', 'name' => 'Staff Bulanan', 'pay_day' => 25, 'cut_off_day' => 20,
        ])
        ->assertSessionHas('success');

    $payday = Payday::forTenant($this->tenant->id)->where('code', 'PD-STAFF')->firstOrFail();
    expect($payday->pay_day)->toBe(25);

    $employees = Employee::forTenant($this->tenant->id)->limit(2)->pluck('id')->all();
    actingAs($this->admin)
        ->post(route('avana.payroll.payday.assign', $payday), ['employee_ids' => $employees])
        ->assertSessionHas('success');

    expect($payday->employees()->count())->toBe(2);
});

it('rejects a duplicate payday code within the tenant', function (): void {
    Payday::create(['tenant_id' => $this->tenant->id, 'code' => 'PD-STAFF', 'name' => 'A', 'pay_day' => 25]);

    actingAs($this->admin)
        ->post(route('avana.payroll.payday.store'), ['code' => 'PD-STAFF', 'name' => 'B', 'pay_day' => 1])
        ->assertSessionHasErrors('code');
});

it('forbids a non-privileged user without payroll permission', function (): void {
    $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

    actingAs($outsider)->get(route('avana.payroll.nilai-upah'))->assertForbidden();
    actingAs($outsider)->get(route('avana.payroll.payday'))->assertForbidden();
});
