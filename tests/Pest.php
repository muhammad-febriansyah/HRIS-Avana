<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\OvertimeRequest;
use App\Models\Partner;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryMaster;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\ReferralPartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A signed-in referral partner: the `partner`-role user plus its Partner
 * profile, the same shape {@see ReferralPartnerService::approve()}
 * provisions. Requires AvanaDemoSeeder to have run, for the `partner` role.
 *
 * @param  array<string, mixed>  $overrides
 */
function createTestPartner(array $overrides = []): Partner
{
    static $sequence = 0;
    $sequence++;

    $user = User::create([
        'tenant_id' => null,
        'name' => 'Mitra Uji '.$sequence,
        'email' => 'mitra.uji'.$sequence.'@example.test',
        'password' => 'password',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $role = Role::query()->whereNull('tenant_id')->where('code', 'partner')->first();

    if ($role !== null) {
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    return Partner::create(array_merge([
        'user_id' => $user->id,
        'code' => 'MITRA'.$sequence,
        'status' => 'active',
    ], $overrides));
}

/**
 * A complete Karyawan form submission, minus the login fields. Every field the
 * form marks required is filled from the seeded tenant's own master data, so a
 * test spells out only the part it is actually about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeFormPayload(int $tenantId, array $overrides = []): array
{
    static $sequence = 0;
    $sequence++;

    return array_merge([
        'full_name' => 'Karyawan Uji '.$sequence,
        'email' => 'karyawan.uji'.$sequence.'@example.test',
        'phone' => '0812'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
        'nik' => (string) (3200000000000000 + $sequence),
        'gender' => 'male',
        'birth_place' => 'Bandung',
        'birth_date' => '1995-05-05',
        'religion' => 'Islam',
        'marital_status' => 'Lajang',
        'employment_status' => 'permanent',
        'join_date' => '2024-01-02',
        'status' => 'active',
        'branch_id' => Branch::where('tenant_id', $tenantId)->value('id'),
        'work_location_id' => WorkLocation::where('tenant_id', $tenantId)->value('id'),
        'department_id' => Department::where('tenant_id', $tenantId)->value('id'),
        'position_id' => Position::where('tenant_id', $tenantId)->value('id'),
        'job_level_id' => JobLevel::where('tenant_id', $tenantId)->value('id'),
        // Not every seeded tenant has a Master Gaji yet, so the payload brings
        // one rather than each test having to.
        'salary_master_id' => SalaryMaster::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'MG-UJI'],
            ['category' => 'Uji', 'is_active' => true],
        )->id,
        'contract_number' => 'PKWT-UJI-'.$sequence,
        'contract_type' => 'pkwt',
        'contract_start_date' => '2024-01-02',
        'contract_end_date' => '2025-01-02',
        'ptkp_status' => 'TK/0',
        'manager_id' => Employee::UNASSIGNED_MANAGER,
    ], $overrides);
}

/**
 * The same submission with the login half filled in, which is what creating an
 * employee needs: a password makes the account, and a role tells it what it may
 * see.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeeCreatePayload(int $tenantId, array $overrides = []): array
{
    return employeeFormPayload($tenantId, array_merge([
        'password' => 'karyawan123',
        'role_id' => Role::where('tenant_id', $tenantId)->value('id'),
    ], $overrides));
}

/**
 * Give an employee a Master Gaji nominal for a component — the primary way
 * payroll now sources amounts. Creates a spec master and assigns it the first
 * time, then upserts the component line (with optional flag overrides).
 *
 * @param  array<string, mixed>  $flags
 */
function giveMasterComponent(
    Employee $employee,
    PayrollComponent $component,
    float $amount,
    array $flags = [],
): SalaryMaster {
    $master = $employee->salary_master_id !== null
        ? SalaryMaster::findOrFail($employee->salary_master_id)
        : SalaryMaster::create([
            'tenant_id' => $employee->tenant_id,
            'code' => 'MG-SPEC-'.$employee->id,
            'category' => 'Spec',
            'is_active' => true,
        ]);

    if ((int) $employee->salary_master_id !== (int) $master->id) {
        $employee->update(['salary_master_id' => $master->id]);
        $employee->refresh();
    }

    $master->components()->updateOrCreate(
        ['payroll_component_id' => $component->id],
        array_merge([
            'included' => true,
            'amount' => $amount,
            'is_prorate' => false,
            'is_kompensasi' => false,
        ], $flags),
    );

    return $master;
}

/**
 * Attendance evidence for an overtime record: present that day and clocked out
 * `$hoursWorked` after the overtime was meant to start, which is what payroll
 * measures payable overtime against.
 */
function seedOvertimeAttendance(
    OvertimeRequest $overtime,
    float $hoursWorked,
    string $startTime = '17:00',
): Attendance {
    $date = $overtime->date->toDateString();
    $start = Carbon::parse($date.' '.$startTime);

    $overtime->forceFill(['start_time' => $startTime])->saveQuietly();

    return Attendance::updateOrCreate(
        [
            'tenant_id' => $overtime->tenant_id,
            'employee_id' => $overtime->employee_id,
            'date' => $date,
        ],
        [
            'branch_id' => $overtime->branch_id,
            'status' => 'present',
            'clock_in_at' => $start->copy()->subHours(8),
            'clock_out_at' => $start->copy()->addMinutes((int) round($hoursWorked * 60)),
        ],
    );
}

/** Seed N present attendance days for an employee inside the period. */
function seedPresentDays(int $tenantId, Employee $employee, PayrollPeriod $period, int $days): void
{
    $date = $period->start_date->copy();
    for ($i = 0; $i < $days; $i++) {
        Attendance::firstOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employee->id, 'date' => $date->toDateString()],
            ['branch_id' => $employee->branch_id, 'status' => 'present'],
        );
        $date->addDay();
    }
}
