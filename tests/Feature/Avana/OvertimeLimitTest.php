<?php

use App\Models\Employee;
use App\Models\OvertimeRate;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OvertimeRules;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    OvertimeRules::forget();

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    // A clean slate: the demo books its own overtime for this employee.
    OvertimeRequest::forTenant($this->tenant->id)->delete();
});

/** File overtime through the admin screen. */
function fileOvertime(object $ctx, string $date, string $start, string $end, array $extra = []): TestResponse
{
    return actingAs($ctx->admin)->post(route('avana.cuti.lembur.store'), array_merge([
        'employee_id' => $ctx->employee->id,
        'date' => $date,
        'start_time' => $start,
        'end_time' => $end,
        'reason' => 'Lembur',
    ], $extra));
}

it('accepts overtime at the four-hour daily ceiling', function (): void {
    fileOvertime($this, '2026-07-06', '17:00', '21:00')->assertSessionHas('success');

    expect((float) OvertimeRequest::latest('id')->firstOrFail()->hours)->toBe(4.0);
});

it('rejects a single stretch over the four-hour daily ceiling', function (): void {
    fileOvertime($this, '2026-07-06', '17:00', '23:00')
        ->assertSessionHasErrors('end_time');

    expect(OvertimeRequest::count())->toBe(0);
});

it('rejects a second request that pushes the same day over the ceiling', function (): void {
    fileOvertime($this, '2026-07-06', '17:00', '20:00')->assertSessionHas('success');
    fileOvertime($this, '2026-07-06', '20:00', '22:00')->assertSessionHasErrors('end_time');

    expect(OvertimeRequest::count())->toBe(1);
});

it('rejects a request that pushes the week over eighteen hours', function (): void {
    // Mon–Fri at 4 hours each is 20 hours; the fifth day breaches the ceiling.
    foreach (['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09'] as $date) {
        fileOvertime($this, $date, '17:00', '21:00')->assertSessionHas('success');
    }

    fileOvertime($this, '2026-07-10', '17:00', '20:00')->assertSessionHasErrors('end_time');

    expect(OvertimeRequest::count())->toBe(4);
});

it('lets a tenant switch the ceilings off', function (): void {
    OvertimeRules::policyFor((int) $this->tenant->id)->update(['enforce_hour_limits' => false]);
    OvertimeRules::forget();

    fileOvertime($this, '2026-07-06', '15:00', '23:00')->assertSessionHas('success');

    expect((float) OvertimeRequest::latest('id')->firstOrFail()->hours)->toBe(8.0);
});

it('stores the submitted day type', function (): void {
    fileOvertime($this, '2026-07-06', '17:00', '20:00', ['day_type' => 'holiday'])
        ->assertSessionHas('success');

    expect(OvertimeRequest::latest('id')->firstOrFail()->day_type)->toBe('holiday');
});

it('classifies a weekend as a rest day when no day type is submitted', function (): void {
    // 2026-07-11 is a Saturday.
    fileOvertime($this, '2026-07-11', '09:00', '12:00')->assertSessionHas('success');

    expect(OvertimeRequest::latest('id')->firstOrFail()->day_type)->toBe('holiday');
});

it('renders the Setup Lembur screen with the statutory table', function (): void {
    actingAs($this->admin)
        ->get(route('avana.payroll.lembur'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-lembur/index', false)
            ->where('policy.max_hours_per_day', 4)
            ->where('policy.max_hours_per_week', 18)
            ->where('policy.hours_divisor', 173)
            ->has('rates', 5)
            ->has('dayTypes', 2)
            ->etc());
});

it('saves an edited multiplier band', function (): void {
    actingAs($this->admin)->get(route('avana.payroll.lembur'))->assertOk();

    actingAs($this->admin)
        ->post(route('avana.payroll.lembur.rate.store'), [
            'day_type' => 'workday',
            'hour_from' => 1,
            'hour_to' => 1,
            'multiplier' => 1.75,
        ])
        ->assertSessionHas('success');

    $band = OvertimeRate::forTenant($this->tenant->id)
        ->where('day_type', 'workday')
        ->where('hour_from', 1)
        ->firstOrFail();

    expect((float) $band->multiplier)->toBe(1.75);
});

it('refuses a band whose end hour precedes its start', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.lembur.rate.store'), [
            'day_type' => 'workday',
            'hour_from' => 5,
            'hour_to' => 2,
            'multiplier' => 2,
        ])
        ->assertSessionHasErrors('hour_to');
});
