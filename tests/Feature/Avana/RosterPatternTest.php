<?php

use App\Models\Employee;
use App\Models\RosterPattern;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Roster;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    [$this->alice, $this->bob] = Employee::forTenant($this->tenant->id)->orderBy('id')->take(2)->get()->all();

    $this->pagi = Shift::forTenant($this->tenant->id)->where('code', 'M')->firstOrFail();
    $this->siang = Shift::forTenant($this->tenant->id)->where('code', 'A')->firstOrFail();
    $this->malam = Shift::forTenant($this->tenant->id)->where('code', 'N')->firstOrFail();

    // 2026-08-10 is a Monday.
    $this->start = '2026-08-10';

    $this->codeOn = function (Employee $employee, string $date): string {
        $schedule = ShiftSchedule::forTenant($this->tenant->id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if ($schedule === null) {
            return '-';
        }

        return $schedule->shift?->code ?? 'O';
    };
});

it('ships the rotation templates the client asked for', function (): void {
    $patterns = RosterPattern::forTenant($this->tenant->id)->with('steps.shift')->get()->keyBy('code');

    expect($patterns->get('MANUFACTURING-3')?->summary())->toBe('3M – 3A – 3N – 2O');
    expect($patterns->get('MANUFACTURING-2')?->summary())->toBe('2M – 2A – 2N – 2O');
    expect($patterns->get('HOSPITAL')?->summary())->toBe('1M – 1A – 1N – 1O');
    expect($patterns->get('MINING')?->cycleDays())->toBe(28);
    expect($patterns->get('OFFSHORE')?->cycleDays())->toBe(56);
    expect($patterns->get('OFFICE')?->cycleDays())->toBe(7);
});

it('lays a 3-3-3-2 rotation across the calendar in order', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'MANUFACTURING-3')->with('steps')->firstOrFail();

    Roster::applyPattern($pattern, (int) $this->alice->id, $this->start, '2026-08-31');

    $actual = collect(range(0, 21))
        ->map(fn (int $offset): string => ($this->codeOn)(
            $this->alice,
            Carbon::parse($this->start)->addDays($offset)->toDateString(),
        ))
        ->implode('');

    // Three mornings, three afternoons, three nights, two off — then again.
    expect($actual)->toBe('MMMAAANNNOO'.'MMMAAANNNOO');
});

it('runs the night leg on a shift that crosses midnight', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'MANUFACTURING-3')->with('steps')->firstOrFail();

    Roster::applyPattern($pattern, (int) $this->alice->id, $this->start, '2026-08-20');

    $night = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->alice->id)
        ->whereDate('date', '2026-08-16')
        ->firstOrFail();

    expect($night->shift?->code)->toBe('N');
    expect(Roster::crossesMidnight($night->shift))->toBeTrue();
});

it('records the off legs as rostered days off, not gaps', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'MANUFACTURING-3')->with('steps')->firstOrFail();

    Roster::applyPattern($pattern, (int) $this->alice->id, $this->start, '2026-08-20');

    $off = ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->alice->id)
        ->whereDate('date', '2026-08-19')
        ->firstOrFail();

    expect($off->shift_id)->toBeNull();
});

it('applies a pattern to a crew from the roster screen', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'HOSPITAL')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.roster.apply-pattern'), [
            'pattern_id' => $pattern->id,
            'employee_ids' => [$this->alice->id, $this->bob->id],
            'start_date' => $this->start,
            'end_date' => '2026-08-17',
        ])
        ->assertSessionHas('success');

    // The whole crew starts the cycle together.
    expect(($this->codeOn)($this->alice, $this->start))->toBe('M');
    expect(($this->codeOn)($this->bob, $this->start))->toBe('M');
    expect(($this->codeOn)($this->alice, '2026-08-13'))->toBe('O');
    expect(($this->codeOn)($this->bob, '2026-08-14'))->toBe('M');
});

it('overwrites what was already on the roster rather than duplicating it', function (): void {
    ShiftSchedule::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->alice->id,
        'shift_id' => $this->malam->id,
        'date' => $this->start,
    ]);

    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'HOSPITAL')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.roster.apply-pattern'), [
            'pattern_id' => $pattern->id,
            'employee_ids' => [$this->alice->id],
            'start_date' => $this->start,
            'end_date' => '2026-08-13',
        ])
        ->assertSessionHas('success');

    expect(ShiftSchedule::forTenant($this->tenant->id)
        ->where('employee_id', $this->alice->id)
        ->whereDate('date', $this->start)
        ->count())->toBe(1);

    expect(($this->codeOn)($this->alice, $this->start))->toBe('M');
});

it('skips the days a leg does not run and says so', function (): void {
    $this->pagi->update(['work_days' => [1, 2, 3, 4, 5]]);

    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'WAREHOUSE')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.roster.apply-pattern'), [
            'pattern_id' => $pattern->id,
            'employee_ids' => [$this->alice->id],
            'start_date' => $this->start,
            'end_date' => '2026-08-23',
        ])
        ->assertSessionHas('success');

    // The second turn of the four-on leg starts on Sunday 2026-08-16.
    expect(($this->codeOn)($this->alice, '2026-08-16'))->toBe('-');
    expect(session('success'))->toContain('dilewati');
});

it('refuses a range longer than a year', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'OFFICE')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.roster.apply-pattern'), [
            'pattern_id' => $pattern->id,
            'employee_ids' => [$this->alice->id],
            'start_date' => '2026-01-01',
            'end_date' => '2028-01-01',
        ])
        ->assertSessionHasErrors('end_date');
});

it('refuses an end date before the start', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'OFFICE')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.roster.apply-pattern'), [
            'pattern_id' => $pattern->id,
            'employee_ids' => [$this->alice->id],
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-10',
        ])
        ->assertSessionHasErrors('end_date');
});

it('creates a pattern with its cycle from the pattern screen', function (): void {
    actingAs($this->admin)
        ->post(route('avana.roster-pola.store'), [
            'code' => 'RETAIL',
            'name' => 'Retail',
            'industry' => 'Toko',
            'status' => 'active',
            'steps' => [
                ['shift_id' => $this->pagi->id, 'days' => 2],
                ['shift_id' => $this->siang->id, 'days' => 2],
                ['shift_id' => null, 'days' => 1],
            ],
        ])
        ->assertSessionHas('success');

    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'RETAIL')->with('steps.shift')->firstOrFail();

    expect($pattern->summary())->toBe('2M – 2A – 1O');
    expect($pattern->cycleDays())->toBe(5);
});

it('replaces the cycle wholesale on edit', function (): void {
    $pattern = RosterPattern::forTenant($this->tenant->id)->where('code', 'HOSPITAL')->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.roster-pola.update', $pattern), [
            'code' => $pattern->code,
            'name' => $pattern->name,
            'status' => 'active',
            'steps' => [
                ['shift_id' => $this->malam->id, 'days' => 4],
                ['shift_id' => null, 'days' => 4],
            ],
        ])
        ->assertSessionHas('success');

    $fresh = $pattern->fresh()->load('steps.shift');

    expect($fresh->steps)->toHaveCount(2);
    expect($fresh->summary())->toBe('4N – 4O');
});

it('rejects a pattern with no steps', function (): void {
    actingAs($this->admin)
        ->post(route('avana.roster-pola.store'), [
            'code' => 'KOSONG',
            'name' => 'Kosong',
            'steps' => [],
        ])
        ->assertSessionHasErrors('steps');
});

it('will not reach a pattern belonging to another tenant', function (): void {
    $other = Tenant::create([
        'name' => 'PT Lain',
        'company_name' => 'PT Lain',
        'slug' => 'pt-lain-uji',
        'status' => 'active',
    ]);

    $foreign = RosterPattern::create([
        'tenant_id' => $other->id,
        'code' => 'ASING',
        'name' => 'Asing',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.roster-pola.update', $foreign), [
            'code' => 'ASING',
            'name' => 'Diambil alih',
            'steps' => [['shift_id' => null, 'days' => 1]],
        ])
        ->assertNotFound();
});
