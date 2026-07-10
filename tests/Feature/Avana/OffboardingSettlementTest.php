<?php

use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\PayrollComponent;
use App\Models\SalaryMaster;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);
    giveMasterComponent($this->employee, $basic, 5_000_000);

    $this->employee->update(['join_date' => '2019-01-01']);

    $this->case = OffboardingCase::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'last_day' => '2026-01-01',
        'reason' => 'Test',
        'status' => 'in_progress',
    ]);
});

it('computes and persists a termination settlement on the case', function (): void {
    // 2019-01-01 → 2026-01-01 = 7 yr → UP 8 months, UPMK 3 months at 5.000.000.
    actingAs($this->admin)
        ->post(route('avana.offboarding.settlement', $this->case), [
            'reason' => 'phk_biasa',
            'uph' => 1_000_000,
        ])
        ->assertSessionHas('success');

    $case = $this->case->fresh();

    expect($case->settlement_reason)->toBe('phk_biasa');
    expect((float) $case->settlement_amount)->toBe(56_000_000.0); // 40jt + 15jt + 1jt
    expect($case->settlement_breakdown['up_months'])->toBe(8);
    expect($case->settlement_breakdown['upmk_months'])->toBe(3);
});

it('uses only the Komponen Kompensasi flagged components as the severance base', function (): void {
    // Full wage = BASIC 5jt + Tunjangan Tetap 2jt = 7jt, but the master flags
    // only BASIC as Komponen Kompensasi, so the base must be 5jt.
    $tetap = PayrollComponent::create([
        'tenant_id' => $this->tenant->id, 'code' => 'TJ-TTP', 'name' => 'Tunjangan Tetap',
        'type' => 'earning', 'component_group' => 'penerimaan', 'is_taxable' => true, 'status' => 'active', 'calc_basis' => 'fixed',
    ]);

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $master = SalaryMaster::create(['tenant_id' => $this->tenant->id, 'code' => 'MG-KOMP', 'category' => 'Organik']);
    // BASIC is the only Komponen Kompensasi flagged line; Tetap is included but
    // not flagged, so it is excluded from the severance base.
    $master->components()->create(['payroll_component_id' => $basic->id, 'included' => true, 'amount' => 5_000_000, 'is_kompensasi' => true]);
    $master->components()->create(['payroll_component_id' => $tetap->id, 'included' => true, 'amount' => 2_000_000]);
    $this->employee->update(['salary_master_id' => $master->id]);

    actingAs($this->admin)
        ->post(route('avana.offboarding.settlement', $this->case), ['reason' => 'phk_biasa'])
        ->assertSessionHas('success');

    $case = $this->case->fresh();
    // 7 yr → UP 8 + UPMK 3 = 11 months x 5jt (base = flagged BASIC only).
    expect((float) $case->settlement_breakdown['monthly_wage'])->toBe(5_000_000.0);
    expect((float) $case->settlement_amount)->toBe(55_000_000.0);
});

it('rejects an unknown termination reason', function (): void {
    actingAs($this->admin)
        ->post(route('avana.offboarding.settlement', $this->case), [
            'reason' => 'karena-mau',
        ])
        ->assertSessionHasErrors('reason');
});
