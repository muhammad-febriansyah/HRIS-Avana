<?php

use App\Console\Commands\RemindAttendance;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantTime;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    $this->setZone = function (string $zone): void {
        $this->tenant->update(['timezone' => $zone]);
        TenantTime::forget();
    };
});

afterEach(function (): void {
    Carbon::setTestNow();
    TenantTime::forget();
});

it('counts attendance against the tenant day, not the server day', function (): void {
    ($this->setZone)('Asia/Jayapura');

    // 23:30 in Jakarta is 01:30 the next morning in Jayapura, so the two
    // disagree about which date "today" is.
    Carbon::setTestNow(Carbon::parse('2026-07-01 23:30:00', 'Asia/Jakarta'));

    $employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->firstOrFail();

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'date' => '2026-07-02',
        'clock_in_at' => '2026-07-02 08:00:00',
        'status' => 'present',
    ]);

    actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props'];

            $present = collect($props['kpis'])->firstWhere('label', 'Hadir Hari Ini');

            expect($props['today'])->toContain('02 Jul 2026')
                ->and($present['value'])->toBe('1');
        });
});

it('defaults the attendance screen to the tenant\'s own date', function (): void {
    ($this->setZone)('Asia/Jayapura');

    Carbon::setTestNow(Carbon::parse('2026-07-01 23:30:00', 'Asia/Jakarta'));

    actingAs($this->admin)
        ->get(route('avana.absensi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.date_from', '2026-07-02'));
});

it('reminds a tenant at its own eight in the morning', function (): void {
    ($this->setZone)('Asia/Jayapura');

    // 08:30 WIT — the tenant's reminder hour, two hours before the server's.
    Carbon::setTestNow(Carbon::parse('2026-07-02 08:30:00', 'Asia/Jayapura'));

    $due = app(RemindAttendance::class)->dueEmployees();

    expect($due)->not->toBeEmpty();
});

it('does not remind a tenant at the server\'s eight in the morning', function (): void {
    ($this->setZone)('Asia/Jayapura');

    // 08:30 WIB is only 06:30 for this tenant; nobody is late yet.
    Carbon::setTestNow(Carbon::parse('2026-07-02 08:30:00', 'Asia/Jakarta'));

    $due = app(RemindAttendance::class)->dueEmployees();

    expect($due)->toBeEmpty();
});
