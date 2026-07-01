<?php

use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\PayrollComponent;
use App\Models\PositionPayrollComponent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);
    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $this->employee->position_id, 'payroll_component_id' => $basic->id],
        ['tenant_id' => $this->tenant->id, 'amount' => 5_000_000],
    );

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

it('rejects an unknown termination reason', function (): void {
    actingAs($this->admin)
        ->post(route('avana.offboarding.settlement', $this->case), [
            'reason' => 'karena-mau',
        ])
        ->assertSessionHasErrors('reason');
});
