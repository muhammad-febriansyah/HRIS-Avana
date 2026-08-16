<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * A finalised payroll is history. Unlocking keeps it; recomputing opens the next
 * revision instead of overwriting what the payslips already stated.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->approver = User::where('tenant_id', $this->admin->tenant_id)->where('id', '!=', $this->admin->id)->firstOrFail();
    $this->approver->roles()->syncWithoutDetaching($this->admin->roles()->pluck('roles.id'));
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-rev')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('payroll/approve', [PayrollController::class, 'approve']);
        Route::post('payroll/lock', [PayrollController::class, 'lock']);
        Route::post('payroll/unlock', [PayrollController::class, 'unlock']);
    });
});

/** Compute, approve and lock the period, and return the finalised run. */
function finalisePeriod(object $ctx): PayrollRun
{
    actingAs($ctx->admin)->post('spec-rev/payroll/run')->assertSessionHas('success');
    actingAs($ctx->approver)->post('spec-rev/payroll/approve', ['payroll_period_id' => $ctx->period->id])->assertSessionHas('success');
    actingAs($ctx->admin)->post('spec-rev/payroll/lock', ['payroll_period_id' => $ctx->period->id])->assertSessionHas('success');

    return PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->orderByDesc('id')->firstOrFail();
}

it('keeps the finalised run and its snapshot after an unlock and recalculation', function (): void {
    $locked = finalisePeriod($this);
    $lockedTotals = [(float) $locked->total_gross, (float) $locked->total_net];
    $lockedItemIds = PayrollRunItem::where('payroll_run_id', $locked->id)->pluck('id');

    actingAs($this->admin)
        ->post('spec-rev/payroll/unlock', ['payroll_period_id' => $this->period->id, 'reason' => 'Koreksi lembur'])
        ->assertSessionHas('success');

    $locked->refresh();

    expect($locked->status)->toBe(PayrollRun::STATUS_LOCKED)
        ->and($locked->superseded_at)->not->toBeNull();

    actingAs($this->admin)->post('spec-rev/payroll/run')->assertSessionHas('success');

    $latest = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->orderByDesc('id')->firstOrFail();

    expect($latest->id)->not->toBe($locked->id)
        ->and((int) $latest->revision)->toBe((int) $locked->revision + 1)
        ->and($latest->superseded_at)->toBeNull();

    // The paid revision is untouched: same totals, same run items.
    $locked->refresh();
    expect([(float) $locked->total_gross, (float) $locked->total_net])->toBe($lockedTotals)
        ->and(PayrollRunItem::where('payroll_run_id', $locked->id)->pluck('id')->all())->toBe($lockedItemIds->all());
});

it('refuses to approve or lock a run whose inputs changed afterwards', function (): void {
    actingAs($this->admin)->post('spec-rev/payroll/run')->assertSessionHas('success');

    // A payroll component edited after the run was computed. Time is moved on
    // first: Eloquent stamps its own updated_at, so a hand-written future
    // timestamp would be overwritten by the update itself.
    $this->travel(5)->minutes();

    PayrollComponent::forTenant($this->tenant->id)
        ->limit(1)
        ->update(['show_on_slip' => true]);

    actingAs($this->approver)
        ->post('spec-rev/payroll/approve', ['payroll_period_id' => $this->period->id])
        ->assertSessionHasErrors('payroll');

    // Recomputing clears it, and the run can then be approved and locked.
    actingAs($this->admin)->post('spec-rev/payroll/run')->assertSessionHas('success');
    actingAs($this->approver)->post('spec-rev/payroll/approve', ['payroll_period_id' => $this->period->id])->assertSessionHas('success');

    // Now a transaction inside the period itself: an attendance the run counted.
    $this->travel(5)->minutes();

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $this->period->start_date->copy()->addDay()->toDateString(),
        'status' => 'present',
        'clock_in_at' => $this->period->start_date->copy()->addDay()->setTime(8, 0),
        'clock_out_at' => $this->period->start_date->copy()->addDay()->setTime(17, 0),
    ]);

    actingAs($this->admin)
        ->post('spec-rev/payroll/lock', ['payroll_period_id' => $this->period->id])
        ->assertSessionHasErrors('payroll');
});

it('refuses a payroll period that overlaps an existing one', function (): void {
    $existing = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.payroll.periods.store'), [
            'name' => 'Periode Beririsan',
            'cycle' => 'monthly',
            'start_date' => $existing->end_date->copy()->subDays(3)->toDateString(),
            'end_date' => $existing->end_date->copy()->addDays(20)->toDateString(),
        ])
        ->assertSessionHasErrors('start_date');

    // A range that starts after the existing one ends is fine.
    actingAs($this->admin)
        ->post(route('avana.payroll.periods.store'), [
            'name' => 'Periode Berikutnya',
            'cycle' => 'monthly',
            'start_date' => $existing->end_date->copy()->addDay()->toDateString(),
            'end_date' => $existing->end_date->copy()->addDays(30)->toDateString(),
        ])
        ->assertSessionHasNoErrors();
});

it('shows a notice instead of a 500 when the sample slip has no PTKP status', function (): void {
    // Every tax profile emptied: the sample slip on the Payroll page must not
    // take the page down with it.
    TaxProfile::where('tenant_id', $this->tenant->id)->update(['ptkp_status' => null]);

    actingAs($this->admin)
        ->get(route('avana.payroll'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('slip.notice', fn (?string $notice): bool => $notice !== null && str_contains($notice, 'PTKP'))
            ->etc());

    // Running payroll still refuses, naming the employees to fix.
    actingAs($this->admin)
        ->post('spec-rev/payroll/run')
        ->assertSessionHasErrors('payroll');
});
