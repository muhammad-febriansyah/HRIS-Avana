<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\IncentiveAssignment;
use App\Models\IncentiveCalculation;
use App\Models\IncentiveScheme;
use App\Models\MenuItem;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * Insentif: scheme → rules → assignment → calculation → approval → payroll.
 * Only an approved incentive is money, and a re-run never pays it twice.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    // The Insentif menu ships switched off — incentives are paid as a salary
    // component for now — and its screens are closed with it. These tests are
    // about the engine behind that screen, so the tenant turns the menu back
    // on the way a tenant would in the Menu Builder.
    MenuItem::where('tenant_id', $this->tenant->id)
        ->where('key', 'payroll-insentif')
        ->update(['is_active' => true]);

    // A second HR account, so approving somebody else's work is possible.
    $this->approver = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Approver Insentif',
        'email' => 'approver.insentif@nusantara.co.id',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    $this->approver->roles()->sync([
        Role::where('tenant_id', $this->tenant->id)->where('code', 'admin_tenant_hr')->value('id'),
    ]);

    Route::middleware('web')->prefix('spec-ins')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('payroll/approve', [PayrollController::class, 'approve']);
        Route::post('payroll/lock', [PayrollController::class, 'lock']);
    });
});

/** A scheme paying a flat amount for 10+ present days. */
function attendanceScheme(object $ctx, array $overrides = []): IncentiveScheme
{
    $component = PayrollComponent::forTenant($ctx->tenant->id)->where('code', 'TJ-KHD')->first()
        ?? PayrollComponent::forTenant($ctx->tenant->id)->firstOrFail();

    $scheme = IncentiveScheme::create(array_merge([
        'tenant_id' => $ctx->tenant->id,
        'code' => 'INS-HADIR',
        'name' => 'Insentif Kehadiran',
        'basis' => IncentiveScheme::BASIS_ATTENDANCE,
        'payroll_component_id' => $component->id,
        'effective_start_date' => $ctx->period->start_date->toDateString(),
        'status' => 'active',
    ], $overrides));

    $scheme->rules()->create([
        'tenant_id' => $ctx->tenant->id,
        'sequence' => 1,
        'min_value' => 10,
        'max_value' => null,
        'amount_type' => 'fixed',
        'amount' => 500_000,
    ]);

    return $scheme->fresh('rules');
}

/** Assign the scheme to the test employee from the period's first day. */
function assignScheme(object $ctx, IncentiveScheme $scheme): IncentiveAssignment
{
    return IncentiveAssignment::create([
        'tenant_id' => $ctx->tenant->id,
        'incentive_scheme_id' => $scheme->id,
        'employee_id' => $ctx->employee->id,
        'effective_start_date' => $ctx->period->start_date->toDateString(),
        'status' => 'active',
        'created_by' => $ctx->admin->id,
    ]);
}

/** Run payroll and return this employee's item. */
function incentiveItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-ins/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();
}

/** The rupiah paid for a named earning line. */
function incentiveLine(PayrollRunItem $item, string $name): float
{
    $row = collect($item->calculation_snapshot['earnings'])->firstWhere('name', $name);

    return (float) ($row['amount'] ?? 0);
}

it('renders the insentif screen', function (): void {
    attendanceScheme($this);

    actingAs($this->admin)
        ->get(route('avana.payroll.insentif'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll-insentif/index', false)
            ->has('schemes', 1)
            ->has('periods')
            ->etc());
});

it('computes an incentive from the band the employee lands in', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id])
        ->assertSessionHas('success');

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();

    expect((float) $row->measured_value)->toBe(12.0)
        ->and((float) $row->amount)->toBe(500_000.0)
        ->and($row->status)->toBe(IncentiveCalculation::STATUS_DRAFT)
        ->and((float) $row->source_snapshot['rule']['amount'])->toBe(500_000.0);
});

it('pays nothing when the employee misses the band', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 3);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    expect((float) IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->value('amount'))->toBe(0.0);
});

it('keeps an unapproved incentive out of payroll', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $component = PayrollComponent::find($scheme->payroll_component_id);

    expect(incentiveLine(incentiveItem($this), (string) $component->name))->toBe(0.0);
});

it('pays an approved incentive exactly once, however often payroll re-runs', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.submit'), ['calculation_ids' => [$row->id]])
        ->assertSessionHas('success');

    actingAs($this->approver)
        ->post(route('avana.payroll.insentif.approve'), ['calculation_ids' => [$row->id]])
        ->assertSessionHas('success');

    $component = PayrollComponent::find($scheme->payroll_component_id);

    $first = incentiveLine(incentiveItem($this), (string) $component->name);
    $second = incentiveLine(incentiveItem($this), (string) $component->name);

    // The component itself may also carry a salary nominal; what matters is
    // that a second run does not add the incentive again.
    expect($second)->toBe($first)
        ->and($first)->toBeGreaterThanOrEqual(500_000.0)
        ->and(IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->count())->toBe(1);
});

it('refuses to let the preparer approve their own incentive', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();

    actingAs($this->admin)->post(route('avana.payroll.insentif.submit'), ['calculation_ids' => [$row->id]]);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.approve'), ['calculation_ids' => [$row->id]])
        ->assertSessionHasErrors('calculation_ids');

    expect($row->fresh()->status)->toBe(IncentiveCalculation::STATUS_PENDING);
});

it('demands a reason for a manual override', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.payroll.insentif.perhitungan.update', $row), ['amount' => 750_000])
        ->assertSessionHasErrors('reason');

    actingAs($this->admin)
        ->put(route('avana.payroll.insentif.perhitungan.update', $row), [
            'amount' => 750_000,
            'reason' => 'Pencapaian tambahan disepakati manajer',
        ])
        ->assertSessionHas('success');

    expect((float) $row->fresh()->amount)->toBe(750_000.0)
        ->and((float) $row->fresh()->computed_amount)->toBe(500_000.0);
});

it('leaves approved rows alone when the period is recalculated', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();
    actingAs($this->admin)->post(route('avana.payroll.insentif.submit'), ['calculation_ids' => [$row->id]]);
    actingAs($this->approver)->post(route('avana.payroll.insentif.approve'), ['calculation_ids' => [$row->id]]);

    // More attendance would raise the figure, but the approved row stands.
    Attendance::forTenant($this->tenant->id)->where('employee_id', $this->employee->id)->delete();

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id])
        ->assertSessionHas('success');

    expect($row->fresh()->status)->toBe(IncentiveCalculation::STATUS_APPROVED)
        ->and((float) $row->fresh()->amount)->toBe(500_000.0);
});

it('locks the incentives a locked period paid', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();
    actingAs($this->admin)->post(route('avana.payroll.insentif.submit'), ['calculation_ids' => [$row->id]]);
    actingAs($this->approver)->post(route('avana.payroll.insentif.approve'), ['calculation_ids' => [$row->id]]);

    incentiveItem($this);

    actingAs($this->admin)->post('spec-ins/payroll/approve', ['payroll_period_id' => $this->period->id]);
    actingAs($this->admin)->post('spec-ins/payroll/lock', ['payroll_period_id' => $this->period->id])->assertSessionHas('success');

    expect($row->fresh()->status)->toBe(IncentiveCalculation::STATUS_LOCKED);

    // A locked incentive is history: it cannot be edited or recalculated.
    actingAs($this->admin)
        ->put(route('avana.payroll.insentif.perhitungan.update', $row), ['amount' => 1_000_000, 'reason' => 'coba ubah'])
        ->assertSessionHasErrors('amount');

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id])
        ->assertSessionHasErrors('payroll_period_id');
});

it('keeps schemes and calculations inside their tenant', function (): void {
    $scheme = attendanceScheme($this);

    $otherTenant = Tenant::create(['name' => 'PT Luar', 'slug' => 'pt-luar', 'status' => 'active']);
    $outsider = User::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'HR Luar',
        'email' => 'hr.luar@example.test',
        'password' => bcrypt('password'),
        'status' => 'active',
    ]);
    $outsider->roles()->sync([
        Role::create(['tenant_id' => $otherTenant->id, 'code' => 'admin_tenant_hr', 'name' => 'HR'])->id,
    ]);

    actingAs($outsider)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id])
        ->assertForbidden();
});

it('refuses every incentive mutation once the period is locked', function (): void {
    $scheme = attendanceScheme($this);
    assignScheme($this, $scheme);
    seedPresentDays((int) $this->tenant->id, $this->employee, $this->period, 12);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id]);

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();

    $this->period->update(['status' => 'locked']);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.submit'), ['calculation_ids' => [$row->id]])
        ->assertSessionHasErrors('calculation_ids');

    actingAs($this->approver)
        ->post(route('avana.payroll.insentif.approve'), ['calculation_ids' => [$row->id]])
        ->assertSessionHasErrors('calculation_ids');

    actingAs($this->approver)
        ->post(route('avana.payroll.insentif.reject'), [
            'calculation_ids' => [$row->id],
            'reason' => 'coba tolak',
        ])
        ->assertSessionHasErrors('calculation_ids');

    actingAs($this->admin)
        ->put(route('avana.payroll.insentif.perhitungan.update', $row), [
            'amount' => 1_000_000,
            'reason' => 'coba ubah',
        ])
        ->assertSessionHasErrors('amount');

    expect($row->fresh()->status)->toBe(IncentiveCalculation::STATUS_DRAFT);
});

it('scores a performance incentive from a review inside the period, not the newest one', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->firstOrFail();

    $scheme = IncentiveScheme::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'INS-KPI',
        'name' => 'Insentif Kinerja',
        'basis' => IncentiveScheme::BASIS_PERFORMANCE,
        'payroll_component_id' => $component->id,
        'effective_start_date' => $this->period->start_date->toDateString(),
        'status' => 'active',
    ]);
    $scheme->rules()->create([
        'tenant_id' => $this->tenant->id,
        'sequence' => 1,
        'min_value' => 80,
        'amount_type' => 'fixed',
        'amount' => 1_000_000,
    ]);
    assignScheme($this, $scheme->fresh('rules'));

    $cycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus QA',
        'period_start' => $this->period->start_date->toDateString(),
        'period_end' => $this->period->end_date->toDateString(),
        'status' => 'active',
    ]);

    // The review that belongs to this period scores below the band…
    PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $cycle->id,
        'employee_id' => $this->employee->id,
        'manager_score' => 70,
        'final_score' => 70,
        'calibrated_score' => 70,
        'calibrated_by' => $this->admin->id,
        'calibrated_at' => now(),
        'status' => 'completed',
        'review_date' => $this->period->start_date->copy()->addDays(3)->toDateString(),
    ]);

    // …while a later review, outside the period, would have cleared it.
    $laterCycle = PerformanceCycle::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Siklus QA Berikutnya',
        'period_start' => $this->period->end_date->copy()->addMonth()->startOfMonth()->toDateString(),
        'period_end' => $this->period->end_date->copy()->addMonth()->endOfMonth()->toDateString(),
        'status' => 'active',
    ]);
    PerformanceReview::create([
        'tenant_id' => $this->tenant->id,
        'cycle_id' => $laterCycle->id,
        'employee_id' => $this->employee->id,
        'manager_score' => 95,
        'final_score' => 95,
        'calibrated_score' => 95,
        'calibrated_by' => $this->admin->id,
        'calibrated_at' => now(),
        'status' => 'completed',
        'review_date' => $this->period->end_date->copy()->addMonth()->toDateString(),
    ]);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.hitung', $scheme), ['payroll_period_id' => $this->period->id])
        ->assertSessionHas('success');

    $row = IncentiveCalculation::where('incentive_scheme_id', $scheme->id)->firstOrFail();

    expect((float) $row->measured_value)->toBe(70.0)
        ->and((float) $row->amount)->toBe(0.0);
});

it('refuses overlapping rule bands and overlapping assignments', function (): void {
    $scheme = attendanceScheme($this); // band: 10 ke atas

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.aturan.store', $scheme), [
            'min_value' => 15,
            'max_value' => 30,
            'amount_type' => 'fixed',
            'amount' => 750_000,
        ])
        ->assertSessionHasErrors('min_value');

    // A band below the existing one is fine.
    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.aturan.store', $scheme), [
            'min_value' => 0,
            'max_value' => 9,
            'amount_type' => 'fixed',
            'amount' => 100_000,
        ])
        ->assertSessionHasNoErrors();

    assignScheme($this, $scheme);

    actingAs($this->admin)
        ->post(route('avana.payroll.insentif.penetapan.store', $scheme), [
            'employee_ids' => [$this->employee->id],
            'effective_start_date' => $this->period->start_date->copy()->addMonth()->toDateString(),
        ])
        ->assertSessionHasErrors('effective_start_date');
});
