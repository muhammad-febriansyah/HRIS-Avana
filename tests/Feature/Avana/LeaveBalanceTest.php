<?php

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LeaveApproval;
use App\Services\LeaveBalanceProvisioner;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->year = (int) now()->year;
    $this->annual = LeaveType::forTenant($this->tenant->id)->where('code', 'TAHUNAN')->firstOrFail();
});

/**
 * A tenant with nothing seeded: the state every real customer starts in, where
 * no balance row has ever been written.
 */
function bareTenant(): array
{
    $tenant = Tenant::create(['name' => 'PT Kosong', 'slug' => 'pt-kosong-saldo']);

    $type = LeaveType::create([
        'tenant_id' => $tenant->id,
        'code' => 'TAHUNAN',
        'name' => 'Cuti Tahunan',
        'default_quota' => 12,
        'status' => 'active',
    ]);

    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'full_name' => 'Karyawan Kosong',
        'employee_number' => 'EMP-KOSONG',
        'status' => 'active',
    ]);

    return [$tenant, $type, $employee];
}

it('opens a balance row for every active employee and quota-owning type', function (): void {
    [$tenant, $type, $employee] = bareTenant();

    // Wipe what the hire hook already opened, so this exercises the bulk run
    // a tenant does at the start of a year.
    LeaveBalance::forTenant($tenant->id)->delete();

    $created = LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);

    expect($created)->toBe(1);

    $balance = LeaveBalance::forTenant($tenant->id)->firstOrFail();

    expect((int) $balance->employee_id)->toBe((int) $employee->id)
        ->and((int) $balance->leave_type_id)->toBe((int) $type->id)
        ->and((float) $balance->quota)->toBe(12.0)
        ->and((float) $balance->used)->toBe(0.0)
        ->and((float) $balance->remaining)->toBe(12.0);
});

it('leaves an HR-adjusted quota alone when run again', function (): void {
    [$tenant] = bareTenant();

    LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);
    LeaveBalance::forTenant($tenant->id)->firstOrFail()->update(['quota' => 20, 'remaining' => 20]);

    $created = LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);

    expect($created)->toBe(0)
        ->and((float) LeaveBalance::forTenant($tenant->id)->firstOrFail()->quota)->toBe(20.0);
});

it('counts leave already approved before the balance existed', function (): void {
    // Approving leave with no balance row deducted nothing, so the days have to
    // be recovered from the requests themselves when the year is opened.
    [$tenant, $type, $employee] = bareTenant();

    LeaveRequest::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => now()->startOfYear()->addDays(10)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(12)->toDateString(),
        'total_days' => 3,
        'status' => 'approved',
    ]);

    LeaveBalance::forTenant($tenant->id)->delete();
    LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);

    $balance = LeaveBalance::forTenant($tenant->id)->firstOrFail();

    expect((float) $balance->used)->toBe(3.0)
        ->and((float) $balance->remaining)->toBe(9.0);
});

it('books a sub-type against its parent balance', function (): void {
    [$tenant, $type, $employee] = bareTenant();

    $sub = LeaveType::create([
        'tenant_id' => $tenant->id,
        'code' => 'TAHUNAN-BERSAMA',
        'name' => 'Cuti Bersama',
        'parent_id' => $type->id,
        'default_quota' => 0,
        'status' => 'active',
    ]);

    LeaveRequest::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $sub->id,
        'start_date' => now()->startOfYear()->addDays(5)->toDateString(),
        'end_date' => now()->startOfYear()->addDays(6)->toDateString(),
        'total_days' => 2,
        'status' => 'approved',
    ]);

    LeaveBalance::forTenant($tenant->id)->delete();
    LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);

    // One row only — the sub-type owns no quota of its own.
    expect(LeaveBalance::forTenant($tenant->id)->count())->toBe(1);

    $balance = LeaveBalance::forTenant($tenant->id)->firstOrFail();

    expect((int) $balance->leave_type_id)->toBe((int) $type->id)
        ->and((float) $balance->used)->toBe(2.0);
});

it('gives a new hire their balances straight away', function (): void {
    [$tenant, $type] = bareTenant();

    $hire = Employee::create([
        'tenant_id' => $tenant->id,
        'full_name' => 'Karyawan Baru',
        'employee_number' => 'EMP-BARU',
        'status' => 'active',
    ]);

    $balance = LeaveBalance::forTenant($tenant->id)
        ->where('employee_id', $hire->id)
        ->where('leave_type_id', $type->id)
        ->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->quota)->toBe(12.0);
});

it('carries last year leftovers without stacking on a rerun', function (): void {
    [$tenant, $type, $employee] = bareTenant();

    LeaveBalance::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'year' => $this->year - 1,
        'quota' => 12,
        'used' => 8,
        'remaining' => 4,
    ]);

    LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);

    LeaveBalanceProvisioner::carryOver((int) $tenant->id, $this->year - 1, $this->year);
    LeaveBalanceProvisioner::carryOver((int) $tenant->id, $this->year - 1, $this->year);

    $balance = LeaveBalance::forTenant($tenant->id)->where('year', $this->year)->firstOrFail();

    expect((float) $balance->quota)->toBe(16.0)
        ->and((float) $balance->remaining)->toBe(16.0);
});

it('caps the carried days when the tenant limits them', function (): void {
    [$tenant, $type, $employee] = bareTenant();

    LeaveBalance::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'year' => $this->year - 1,
        'quota' => 12,
        'used' => 2,
        'remaining' => 10,
    ]);

    LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);
    LeaveBalanceProvisioner::carryOver((int) $tenant->id, $this->year - 1, $this->year, 6);

    expect((float) LeaveBalance::forTenant($tenant->id)->where('year', $this->year)->firstOrFail()->quota)
        ->toBe(18.0);
});

it('deducts an approved leave once a balance exists', function (): void {
    [$tenant, $type, $employee] = bareTenant();

    LeaveBalanceProvisioner::forTenant((int) $tenant->id, $this->year);

    $leave = LeaveRequest::create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'total_days' => 2,
        'status' => 'pending',
    ]);

    LeaveApproval::finalize($leave);

    $balance = LeaveBalance::forTenant($tenant->id)->firstOrFail();

    expect((float) $balance->used)->toBe(2.0)
        ->and((float) $balance->remaining)->toBe(10.0);
});

it('runs the yearly command for one tenant', function (): void {
    [$tenant] = bareTenant();

    LeaveBalance::forTenant($tenant->id)->delete();

    $this->artisan('avana:generate-leave-balance', ['year' => $this->year, '--tenant' => $tenant->id])
        ->assertSuccessful();

    expect(LeaveBalance::forTenant($tenant->id)->where('year', $this->year)->count())->toBe(1);
});

it('refuses a nonsense year', function (): void {
    $this->artisan('avana:generate-leave-balance', ['year' => 1200])->assertFailed();
});

it('renders the saldo cuti screen with rows and coverage counts', function (): void {
    actingAs($this->admin)
        ->get(route('avana.cuti.saldo'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/saldo-cuti/index', false)
            ->has('rows.data.0.balances')
            ->has('leaveTypes')
            ->has('kpis.uncovered')
            ->where('filters.year', $this->year));
});

it('opens the year from the screen', function (): void {
    LeaveBalance::forTenant($this->tenant->id)->delete();

    actingAs($this->admin)
        ->post(route('avana.cuti.saldo.generate'), ['year' => $this->year])
        ->assertRedirect();

    expect(LeaveBalance::forTenant($this->tenant->id)->where('year', $this->year)->count())
        ->toBeGreaterThan(0);
});

it('overrides one quota and keeps the used days', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    LeaveBalance::updateOrCreate(
        [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual->id,
            'year' => $this->year,
        ],
        ['quota' => 12, 'used' => 3, 'remaining' => 9],
    );

    actingAs($this->admin)
        ->put(route('avana.cuti.saldo.update'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual->id,
            'year' => $this->year,
            'quota' => 18,
        ])
        ->assertRedirect();

    $balance = LeaveBalance::forTenant($this->tenant->id)
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $this->annual->id)
        ->where('year', $this->year)
        ->firstOrFail();

    expect((float) $balance->quota)->toBe(18.0)
        ->and((float) $balance->used)->toBe(3.0)
        ->and((float) $balance->remaining)->toBe(15.0);
});

it('rejects a quota beyond a year of days', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.cuti.saldo.update'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $this->annual->id,
            'year' => $this->year,
            'quota' => 900,
        ])
        ->assertSessionHasErrors('quota');
});

it('imports quotas from a spreadsheet', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->whereNotNull('employee_number')->firstOrFail();

    $csv = "nomor_karyawan,nama,jenis_cuti,kuota_hari\n"
        ."{$employee->employee_number},{$employee->full_name},{$this->annual->name},\"15,5\"\n";

    actingAs($this->admin)
        ->post(route('avana.cuti.saldo.import'), [
            'year' => $this->year,
            'file' => UploadedFile::fake()->createWithContent('saldo.csv', $csv),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $balance = LeaveBalance::forTenant($this->tenant->id)
        ->where('employee_id', $employee->id)
        ->where('leave_type_id', $this->annual->id)
        ->where('year', $this->year)
        ->firstOrFail();

    expect((float) $balance->quota)->toBe(15.5);
});

it('rejects the whole file when one row names nobody', function (): void {
    $csv = "nomor_karyawan,nama,jenis_cuti,kuota_hari\n"
        ."EMP-HANTU,Tidak Ada,{$this->annual->name},10\n";

    actingAs($this->admin)
        ->post(route('avana.cuti.saldo.import'), [
            'year' => $this->year,
            'file' => UploadedFile::fake()->createWithContent('saldo.csv', $csv),
        ])
        ->assertSessionHasErrors('file');
});

it('shows real remaining days on the Cuti screen instead of a full bar', function (): void {
    // The cards used to read the leave type's default quota with a hardcoded
    // "100%", so they stayed full however much leave had been taken.
    LeaveBalance::forTenant($this->tenant->id)->where('year', $this->year)->delete();

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    LeaveBalance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $this->annual->id,
        'year' => $this->year,
        'quota' => 12,
        'used' => 3,
        'remaining' => 9,
    ]);

    actingAs($this->admin)
        ->get(route('avana.cuti'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $balances = collect($page->toArray()['props']['balances']);
            $annual = $balances->firstWhere('id', $this->annual->id);

            expect((float) $annual['total'])->toBe(12.0)
                ->and((float) $annual['sisa'])->toBe(9.0)
                ->and($annual['pct'])->toBe('75%');
        });
});

it('forbids an employee from reading the saldo screen', function (): void {
    $plain = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $plain->roles()->sync([
        Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail()->id,
    ]);

    actingAs($plain)
        ->get(route('avana.cuti.saldo'))
        ->assertForbidden();
});
