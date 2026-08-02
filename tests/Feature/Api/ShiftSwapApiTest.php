<?php

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftSchedule;
use App\Models\ShiftSwap;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->employee->tenant_id;

    $this->colleague = Employee::forTenant($tenantId)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();

    $this->pagi = Shift::create([
        'tenant_id' => $tenantId,
        'code' => 'PG-API', 'name' => 'Pagi',
        'start_time' => '08:00:00', 'end_time' => '16:00:00',
        'late_tolerance_minutes' => 5, 'status' => 'active',
    ]);

    $this->siang = Shift::create([
        'tenant_id' => $tenantId,
        'code' => 'SG-API', 'name' => 'Siang',
        'start_time' => '14:00:00', 'end_time' => '22:00:00',
        'late_tolerance_minutes' => 5, 'status' => 'active',
    ]);

    $this->tomorrow = now()->addDay()->toDateString();

    $this->roster = function (Employee $employee, ?int $shiftId, ?string $date = null): ShiftSchedule {
        return ShiftSchedule::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'shift_id' => $shiftId,
            'date' => $date ?? $this->tomorrow,
        ]);
    };
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('records the shifts being traded when a swap is requested', function (): void {
    ($this->roster)($this->employee, $this->pagi->id);
    ($this->roster)($this->colleague, $this->siang->id);

    ($this->auth)()
        ->postJson('/api/v1/me/shift-swaps', [
            'target_id' => $this->colleague->id,
            'date' => $this->tomorrow,
            'reason' => 'Ada urusan pagi',
        ])
        ->assertCreated();

    $swap = ShiftSwap::latest('id')->firstOrFail();

    // Without these the approver on the web sees a blank trade.
    expect((int) $swap->requester_shift_id)->toBe($this->pagi->id);
    expect((int) $swap->target_shift_id)->toBe($this->siang->id);
});

it('refuses a swap on a day neither side is rostered', function (): void {
    ($this->auth)()
        ->postJson('/api/v1/me/shift-swaps', [
            'target_id' => $this->colleague->id,
            'date' => $this->tomorrow,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Belum ada jadwal untuk tanggal itu, jadi tidak ada yang bisa ditukar.');

    expect(ShiftSwap::count())->toBe(0);
});

it('accepts a swap when only one side is rostered', function (): void {
    ($this->roster)($this->employee, $this->pagi->id);

    ($this->auth)()
        ->postJson('/api/v1/me/shift-swaps', [
            'target_id' => $this->colleague->id,
            'date' => $this->tomorrow,
        ])
        ->assertCreated();

    $swap = ShiftSwap::latest('id')->firstOrFail();

    expect((int) $swap->requester_shift_id)->toBe($this->pagi->id);
    expect($swap->target_shift_id)->toBeNull();
});

it('refuses a swap for a date that has already passed', function (): void {
    $yesterday = now()->subDay()->toDateString();
    ($this->roster)($this->employee, $this->pagi->id, $yesterday);

    ($this->auth)()
        ->postJson('/api/v1/me/shift-swaps', [
            'target_id' => $this->colleague->id,
            'date' => $yesterday,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('date');
});

it('refuses a second pending swap with the same colleague on the same day', function (): void {
    ($this->roster)($this->employee, $this->pagi->id);
    ($this->roster)($this->colleague, $this->siang->id);

    $payload = ['target_id' => $this->colleague->id, 'date' => $this->tomorrow];

    ($this->auth)()->postJson('/api/v1/me/shift-swaps', $payload)->assertCreated();
    ($this->auth)()->postJson('/api/v1/me/shift-swaps', $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', 'Sudah ada pengajuan tukar shift yang menunggu untuk tanggal itu.');

    expect(ShiftSwap::count())->toBe(1);
});

it('refuses a duplicate raised from the other side of the same pair', function (): void {
    ($this->roster)($this->employee, $this->pagi->id);
    ($this->roster)($this->colleague, $this->siang->id);

    ShiftSwap::create([
        'tenant_id' => $this->employee->tenant_id,
        'requester_id' => $this->colleague->id,
        'target_id' => $this->employee->id,
        'date' => $this->tomorrow,
        'status' => 'pending',
    ]);

    ($this->auth)()
        ->postJson('/api/v1/me/shift-swaps', [
            'target_id' => $this->colleague->id,
            'date' => $this->tomorrow,
        ])
        ->assertStatus(422);
});

it('lists the shifts each side is trading', function (): void {
    ($this->roster)($this->employee, $this->pagi->id);
    ($this->roster)($this->colleague, $this->siang->id);

    ($this->auth)()->postJson('/api/v1/me/shift-swaps', [
        'target_id' => $this->colleague->id,
        'date' => $this->tomorrow,
    ])->assertCreated();

    ($this->auth)()
        ->getJson('/api/v1/me/shift-swaps')
        ->assertOk()
        ->assertJsonPath('data.0.requester_shift', 'Pagi')
        ->assertJsonPath('data.0.target_shift', 'Siang')
        ->assertJsonPath('data.0.direction', 'outgoing');
});

it('reads a rostered day off as Libur rather than an unknown shift', function (): void {
    ($this->roster)($this->employee, null);
    ($this->roster)($this->colleague, $this->siang->id);

    ($this->auth)()->postJson('/api/v1/me/shift-swaps', [
        'target_id' => $this->colleague->id,
        'date' => $this->tomorrow,
    ])->assertCreated();

    ($this->auth)()
        ->getJson('/api/v1/me/shift-swaps')
        ->assertOk()
        ->assertJsonPath('data.0.requester_shift', 'Libur')
        ->assertJsonPath('data.0.target_shift', 'Siang');
});
