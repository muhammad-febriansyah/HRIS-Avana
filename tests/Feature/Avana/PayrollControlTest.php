<?php

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
