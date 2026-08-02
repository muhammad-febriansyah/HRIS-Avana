<?php

use App\Console\Commands\RemindAttendance;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Someone with a login, since the reminder only reaches app users, and
    // with today clear so they are still owed a reminder.
    $this->alice = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->orderBy('id')
        ->firstOrFail();

    Attendance::where('employee_id', $this->alice->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    $this->weekday = Shift::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'WD-SPEC', 'name' => 'Kantor',
        'start_time' => '08:00:00', 'end_time' => '17:00:00',
        'late_tolerance_minutes' => 10,
        'work_days' => [1, 2, 3, 4, 5],
        'status' => 'active',
    ]);
});

it('saves the days a shift runs from the setup form', function (): void {
    actingAs($this->admin)
        ->post('/avana/perusahaan/shifts', [
            'code' => 'AKHIRPEKAN',
            'name' => 'Akhir Pekan',
            'start_time' => '09:00',
            'end_time' => '15:00',
            'late_tolerance_minutes' => 10,
            // The day picker submits a flat string alongside every other field.
            'work_days' => '0,6',
            'status' => 'active',
        ])
        ->assertRedirect();

    $shift = Shift::forTenant($this->tenant->id)->where('code', 'AKHIRPEKAN')->firstOrFail();

    expect($shift->work_days)->toBe([0, 6]);
});

it('reads a blank day picker as a shift that runs every day', function (): void {
    actingAs($this->admin)
        ->put('/avana/perusahaan/shifts/'.$this->weekday->id, [
            'code' => $this->weekday->code,
            'name' => $this->weekday->name,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_tolerance_minutes' => 10,
            'work_days' => '',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($this->weekday->fresh()->work_days)->toBeNull();
});

it('still accepts the days as an array', function (): void {
    actingAs($this->admin)
        ->put('/avana/perusahaan/shifts/'.$this->weekday->id, [
            'code' => $this->weekday->code,
            'name' => $this->weekday->name,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'late_tolerance_minutes' => 10,
            'work_days' => [2, 4],
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($this->weekday->fresh()->work_days)->toBe([2, 4]);
});

it('skips the days a shift does not run when copying last week', function (): void {
    // 2026-08-03 is a Monday; 08-08 and 08-09 are the weekend that follows.
    foreach (['2026-08-03', '2026-08-08', '2026-08-09'] as $date) {
        ShiftSchedule::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->alice->id,
            'shift_id' => $this->weekday->id,
            'date' => $date,
        ]);
    }

    actingAs($this->admin)
        ->post(route('avana.roster.copy-week'), ['week_start' => '2026-08-10'])
        ->assertSessionHas('success');

    $copied = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->alice->id)
        ->whereDate('date', '>=', '2026-08-10')
        ->pluck('date')
        ->map(fn ($date): string => Carbon::parse($date)->toDateString());

    expect($copied)->toContain('2026-08-10');
    expect($copied)->not->toContain('2026-08-15');
    expect($copied)->not->toContain('2026-08-16');
});

it('does not remind someone the roster has marked off', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'shift_id' => null,
        'date' => now()->toDateString(),
    ]);

    $reminded = remindedUserIds();

    expect($reminded)->not->toContain($this->alice->user_id);
});

it('reminds someone rostered to work today', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'shift_id' => $this->weekday->id,
        'date' => now()->toDateString(),
    ]);

    expect(remindedUserIds())->toContain($this->alice->user_id);
});

/**
 * The user ids the reminder would push to today.
 *
 * @return list<int>
 */
function remindedUserIds(): array
{
    return (new RemindAttendance)
        ->dueEmployees(Carbon::today())
        ->pluck('user_id')
        ->all();
}
