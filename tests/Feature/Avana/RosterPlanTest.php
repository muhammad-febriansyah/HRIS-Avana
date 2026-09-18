<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\RosterPlan;
use App\Models\RosterPlanDay;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)
        ->where('status', 'active')
        ->firstOrFail();
});

const PLAN_START = '2026-06-29';
const PLAN_END = '2026-07-05';

function rosterPlanPayload(int $employeeId): array
{
    return [
        'employee_id' => $employeeId,
        'name' => 'Plan WFA Mingguan',
        'shift_label' => 'CSO (WFA)',
        'period_start' => PLAN_START,
        'period_end' => PLAN_END,
        'days' => [
            [
                'date' => PLAN_START,
                'type' => 'jadwal',
                'category' => 'wfa',
                'boundary_start' => '06:00',
                'boundary_end' => '17:00',
                'schedule_start' => '08:00',
                'schedule_end' => '17:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
                'notes' => 'Rencana kerja dari rumah.',
            ],
            [
                'date' => '2026-06-30',
                'type' => 'libur',
                'category' => null,
                'boundary_start' => null,
                'boundary_end' => null,
                'schedule_start' => null,
                'schedule_end' => null,
                'break_start' => null,
                'break_end' => null,
                'notes' => null,
            ],
        ],
    ];
}

it('stores a draft roster plan without changing schedules or attendance', function (): void {
    $schedule = ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'shift_id' => null,
        'date' => PLAN_START,
    ]);
    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => PLAN_START,
        'status' => 'present',
        'late_minutes' => 0,
    ]);

    actingAs($this->admin)
        ->from(route('avana.roster'))
        ->post(route('avana.roster.plans.store'), rosterPlanPayload($this->employee->id))
        ->assertRedirect(route('avana.roster'))
        ->assertSessionHas('success');

    $plan = RosterPlan::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->firstOrFail();

    expect($plan->status)->toBe(RosterPlan::STATUS_DRAFT)
        ->and($plan->days)->toHaveCount(2);

    expect(ShiftSchedule::find($schedule->id)->shift_id)->toBeNull();
    expect(Attendance::find($attendance->id)->status)->toBe('present');
    expect(Attendance::find($attendance->id)->shift_id)->toBeNull();
});

it('updates the existing draft for the same employee and period', function (): void {
    $payload = rosterPlanPayload($this->employee->id);

    actingAs($this->admin)
        ->post(route('avana.roster.plans.store'), $payload)
        ->assertSessionHas('success');

    $payload['name'] = 'Plan Revisi';
    $payload['days'][0]['category'] = 'wfh';

    actingAs($this->admin)
        ->post(route('avana.roster.plans.store'), $payload)
        ->assertSessionHas('success');

    expect(RosterPlan::forTenant($this->tenant->id)->count())->toBe(1);
    expect(RosterPlan::firstOrFail()->name)->toBe('Plan Revisi');
    expect(RosterPlanDay::firstOrFail()->category)->toBe('wfh');
});

it('exposes the draft plan to the roster page for the requested week', function (): void {
    $plan = RosterPlan::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'created_by' => $this->admin->id,
        'name' => 'Draft dari test',
        'period_start' => PLAN_START,
        'period_end' => PLAN_END,
        'status' => RosterPlan::STATUS_DRAFT,
    ]);
    $plan->days()->create([
        'date' => PLAN_START,
        'type' => 'jadwal',
        'category' => 'wfo',
    ]);

    actingAs($this->admin)
        ->get(route('avana.roster', ['week_start' => PLAN_START]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('draft_plans', 1)
            ->where('draft_plans.0.employee_id', $this->employee->id)
            ->where('draft_plans.0.status', RosterPlan::STATUS_DRAFT)
            ->has('draft_plans.0.days', 1));
});

it('rejects duplicate days inside a draft plan', function (): void {
    $payload = rosterPlanPayload($this->employee->id);
    $payload['days'][1]['date'] = PLAN_START;

    actingAs($this->admin)
        ->post(route('avana.roster.plans.store'), $payload)
        ->assertSessionHasErrors('days.1.date');

    expect(RosterPlan::forTenant($this->tenant->id)->count())->toBe(0);
});

it('does not accept an employee from another tenant', function (): void {
    $otherTenant = Tenant::create([
        'name' => 'PT Tenant Lain',
        'slug' => 'tenant-lain-roster-plan',
    ]);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-FOREIGN-PLAN',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(
            route('avana.roster.plans.store'),
            rosterPlanPayload($foreignEmployee->id),
        )
        ->assertSessionHasErrors('employee_id');
});
