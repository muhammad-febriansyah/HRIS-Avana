<?php

use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OvertimeRules;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    OvertimeRules::forget();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();
});

/** Set the tenant's rounding block and clear the cached policy. */
function setRounding(int $tenantId, int $minutes): void
{
    // Drop the cache first: the policy is memoised per process, so a model left
    // over from an earlier spec would take the write to a row that no longer
    // exists once its transaction rolled back.
    OvertimeRules::forget();
    OvertimeRules::policyFor($tenantId)->update(['rounding_minutes' => $minutes]);
    OvertimeRules::forget();
}

/** File an overtime request over the HR desk endpoint. */
function fileRoundedOvertime(User $admin, Employee $employee, string $start, string $end)
{
    return actingAs($admin)->post(route('avana.cuti.lembur.store'), [
        'employee_id' => $employee->id,
        'date' => '2026-05-06',
        'start_time' => $start,
        'end_time' => $end,
    ]);
}

it('rounds a stretch down to the tenant block', function (): void {
    setRounding($this->tenant->id, 30);

    // 18:00–18:45 = 45 minutes, paid as half an hour.
    fileRoundedOvertime($this->admin, $this->employee, '18:00', '18:45')->assertSessionHasNoErrors();

    expect((float) OvertimeRequest::latest('id')->first()->hours)->toBe(0.5);

    // 18:00–19:29 = 89 minutes, paid as one hour.
    fileRoundedOvertime($this->admin, $this->employee, '18:00', '19:29')->assertSessionHasNoErrors();

    expect((float) OvertimeRequest::latest('id')->first()->hours)->toBe(1.0);

    // 18:00–19:59 = 119 minutes, paid as one and a half.
    fileRoundedOvertime($this->admin, $this->employee, '18:00', '19:59')->assertSessionHasNoErrors();

    expect((float) OvertimeRequest::latest('id')->first()->hours)->toBe(1.5);
});

it('refuses a stretch shorter than one block', function (): void {
    setRounding($this->tenant->id, 30);

    fileRoundedOvertime($this->admin, $this->employee, '18:00', '18:20')
        ->assertSessionHasErrors('end_time');

    expect(OvertimeRequest::forTenant($this->tenant->id)->where('date', '2026-05-06')->count())->toBe(0);
});

it('keeps exact hours when the tenant sets no rounding', function (): void {
    setRounding($this->tenant->id, 0);

    fileRoundedOvertime($this->admin, $this->employee, '18:00', '18:45')->assertSessionHasNoErrors();

    expect((float) OvertimeRequest::latest('id')->first()->hours)->toBe(0.75);
});

it('rounds by a block other than half an hour', function (): void {
    setRounding($this->tenant->id, 15);

    // 50 minutes = three blocks of 15 = 0,75 jam.
    fileRoundedOvertime($this->admin, $this->employee, '18:00', '18:50')->assertSessionHasNoErrors();

    expect((float) OvertimeRequest::latest('id')->first()->hours)->toBe(0.75);
});

it('computes the rounding table the same way for every intake', function (): void {
    expect(OvertimeRules::roundHours(0.75, 30))->toBe(0.5);
    expect(OvertimeRules::roundHours(1.48, 30))->toBe(1.0);
    expect(OvertimeRules::roundHours(1.98, 30))->toBe(1.5);
    expect(OvertimeRules::roundHours(0.4, 30))->toBe(0.0);
    expect(OvertimeRules::roundHours(2.0, 30))->toBe(2.0);
    expect(OvertimeRules::roundHours(0.75, 0))->toBe(0.75);
});

it('saves the rounding rule from Setup Lembur', function (): void {
    OvertimeRules::forget();

    actingAs($this->admin)
        ->put(route('avana.payroll.lembur.policy'), [
            'max_hours_per_day' => 4,
            'max_hours_per_week' => 18,
            'hours_divisor' => 173,
            'rounding_minutes' => 30,
            'fixed_basis_min_ratio' => 0.75,
            'enforce_hour_limits' => true,
        ])
        ->assertSessionHasNoErrors();

    OvertimeRules::forget();

    expect((int) OvertimeRules::policyFor($this->tenant->id)->rounding_minutes)->toBe(30);
});
