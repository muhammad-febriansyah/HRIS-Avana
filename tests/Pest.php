<?php

use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\SalaryMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'is_overtime_base' => false,
            'is_kompensasi' => false,
        ], $flags),
    );

    return $master;
}
