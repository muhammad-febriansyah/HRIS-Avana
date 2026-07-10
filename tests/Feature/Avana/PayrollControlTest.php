<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->hrAdmin->tenant_id;

    // A calculated run to operate on.
    actingAs($this->hrAdmin)->post(route('avana.payroll.run'))->assertSessionHas('success');
    $this->run = PayrollRun::forTenant($this->tenantId)->latest('id')->firstOrFail();
});

it('records who ran the payroll', function (): void {
    expect((int) $this->run->run_by)->toBe($this->hrAdmin->id);
    expect($this->run->status)->toBe('calculated');
});

it('updates the period pay date supplied from the run confirmation', function (): void {
    $period = PayrollPeriod::find($this->run->payroll_period_id);

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.run'), ['pay_date' => '2026-06-27'])
        ->assertSessionHas('success');

    expect($period->fresh()->pay_date->toDateString())->toBe('2026-06-27');
});

it('blocks bank transfer & BPJS export until the period is locked', function (): void {
    actingAs($this->hrAdmin)
        ->get(route('avana.payroll.transfer'))
        ->assertSessionHasErrors('payroll');

    actingAs($this->hrAdmin)
        ->get(route('avana.payroll.bpjs.export'))
        ->assertSessionHasErrors('payroll');
});

it('allows bank transfer once the run is locked', function (): void {
    actingAs($this->hrAdmin)->post(route('avana.payroll.approve'))->assertSessionHas('success');
    actingAs($this->hrAdmin)->post(route('avana.payroll.lock'))->assertSessionHas('success');

    actingAs($this->hrAdmin)
        ->get(route('avana.payroll.transfer'))
        ->assertOk();
});

it('lets the runner self-approve when segregation is off (default)', function (): void {
    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.approve'))
        ->assertSessionHas('success');

    expect($this->run->fresh()->status)->toBe('approved');
});

it('blocks the runner from approving their own run when segregation is on', function (): void {
    $this->hrAdmin->tenant->update(['enforce_payroll_segregation' => true]);

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.approve'))
        ->assertSessionHasErrors('payroll');

    expect($this->run->fresh()->status)->toBe('calculated');
});

it('allows approval by a user who did not run it when segregation is on', function (): void {
    $this->hrAdmin->tenant->update(['enforce_payroll_segregation' => true]);
    // Someone else ran it.
    $other = User::where('tenant_id', $this->tenantId)
        ->where('id', '!=', $this->hrAdmin->id)
        ->firstOrFail();
    $this->run->update(['run_by' => $other->id]);

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.approve'))
        ->assertSessionHas('success');

    expect($this->run->fresh()->status)->toBe('approved');
});

it('reopens a locked period back to draft with an authorized reason', function (): void {
    actingAs($this->hrAdmin)->post(route('avana.payroll.approve'))->assertSessionHas('success');
    actingAs($this->hrAdmin)->post(route('avana.payroll.lock'))->assertSessionHas('success');

    $periodId = $this->run->fresh()->payroll_period_id;
    expect(PayrollPeriod::find($periodId)->status)->toBe('locked');

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.unlock'), ['payroll_period_id' => $periodId, 'reason' => 'Koreksi komponen keliru'])
        ->assertSessionHas('success');

    expect(PayrollPeriod::find($periodId)->status)->toBe('draft');
    expect($this->run->fresh()->status)->toBe('approved');
});

it('requires a reason to unlock', function (): void {
    actingAs($this->hrAdmin)->post(route('avana.payroll.approve'))->assertSessionHas('success');
    actingAs($this->hrAdmin)->post(route('avana.payroll.lock'))->assertSessionHas('success');

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.unlock'), ['payroll_period_id' => $this->run->fresh()->payroll_period_id])
        ->assertSessionHasErrors('reason');
});

it('refuses to unlock a period that is not locked', function (): void {
    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.unlock'), ['payroll_period_id' => $this->run->payroll_period_id, 'reason' => 'Belum dikunci'])
        ->assertSessionHasErrors('payroll');
});

it('records the unlock on the audit trail', function (): void {
    actingAs($this->hrAdmin)->post(route('avana.payroll.approve'))->assertSessionHas('success');
    actingAs($this->hrAdmin)->post(route('avana.payroll.lock'))->assertSessionHas('success');

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.unlock'), ['payroll_period_id' => $this->run->fresh()->payroll_period_id, 'reason' => 'Salah tanggal bayar'])
        ->assertSessionHas('success');

    $log = AuditLog::where('tenant_id', $this->tenantId)->where('action', 'payroll_unlocked')->latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['reason'] ?? null)->toBe('Salah tanggal bayar');
    expect((int) $log->user_id)->toBe($this->hrAdmin->id);
});

it('reverses a loan installment advance when unlocking', function (): void {
    $employee = Employee::forTenant($this->tenantId)->where('status', 'active')->orderBy('id')->firstOrFail();

    $loan = Loan::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $employee->id,
        'amount' => 3_000_000,
        'tenor_months' => 6,
        'interest_rate' => 0,
        'monthly_installment' => 500_000,
        'paid_installments' => 0,
        'purpose' => 'Uji unlock',
        'status' => 'approved',
    ]);

    // Recompute so the new loan is picked up into the run snapshot, then finalize.
    actingAs($this->hrAdmin)->post(route('avana.payroll.run'))->assertSessionHas('success');
    actingAs($this->hrAdmin)->post(route('avana.payroll.approve'))->assertSessionHas('success');
    actingAs($this->hrAdmin)->post(route('avana.payroll.lock'))->assertSessionHas('success');

    expect((int) $loan->fresh()->paid_installments)->toBe(1);

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.unlock'), ['payroll_period_id' => $this->run->fresh()->payroll_period_id, 'reason' => 'Reversal cicilan'])
        ->assertSessionHas('success');

    expect((int) $loan->fresh()->paid_installments)->toBe(0);
    expect($loan->fresh()->status)->toBe('approved');
});
